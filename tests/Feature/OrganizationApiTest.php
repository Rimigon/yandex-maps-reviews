<?php

namespace Tests\Feature;

use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum включает сессию для запросов со своего домена, а этот домен
        // он определяет по Origin/Referer — в браузере они есть всегда.
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_неавторизованный_запрос_получает_401(): void
    {
        $this->getJson('/api/organizations')->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_вход_и_выход(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

        $this->postJson('/api/login', ['email' => 'wrong@example.com', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->assertAuthenticatedAs($user, 'web');

        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.email', $user->email);

        $this->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_добавление_организации_ставит_выгрузку_в_очередь(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organizations', ['url' => 'https://yandex.ru/maps/org/test/1234567890/reviews/?ll=37.6%2C55.7'])
            ->assertCreated()
            ->assertJsonPath('data.business_id', '1234567890')
            ->assertJsonPath('data.url', 'https://yandex.ru/maps/org/test/1234567890')
            ->assertJsonPath('data.sync.status', 'queued');

        Queue::assertPushed(SyncOrganizationReviewsJob::class);
        $this->assertDatabaseHas('organizations', ['user_id' => $user->id, 'business_id' => '1234567890']);
    }

    public function test_некорректная_ссылка_отклоняется_с_понятной_ошибкой(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/organizations', ['url' => 'https://2gis.ru/moscow/firm/123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_одну_карточку_нельзя_добавить_дважды_разными_ссылками(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organizations', ['url' => 'https://yandex.ru/maps/org/test/1234567890/reviews/'])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/organizations', ['url' => 'https://yandex.ru/maps/org/test/1234567890/?ll=37.6%2C55.7'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');

        $this->assertSame(1, $user->organizations()->count());
    }

    public function test_чужие_организации_недоступны(): void
    {
        $organization = Organization::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/organizations/{$organization->id}")
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/organizations/{$organization->id}/reviews")
            ->assertForbidden();
    }

    public function test_отзывы_отдаются_страницами_по_50(): void
    {
        $organization = Organization::factory()->for(User::factory())->create();
        Review::factory()->count(120)->for($organization)->create();

        $response = $this->actingAs($organization->user)
            ->getJson("/api/organizations/{$organization->id}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 120);

        $this->assertCount(50, $response->json('data'));

        $page3 = $this->actingAs($organization->user)
            ->getJson("/api/organizations/{$organization->id}/reviews?page=3")
            ->assertOk();

        $this->assertCount(20, $page3->json('data'));
    }

    public function test_повторная_выгрузка_по_свежим_данным_не_запускается_без_force(): void
    {
        Queue::fake();

        $organization = Organization::factory()->for(User::factory())->completed()->create([
            'last_synced_at' => now(),
            'reviews_parsed' => 1,
        ]);

        Review::factory()->for($organization)->create();

        $this->actingAs($organization->user)
            ->postJson("/api/organizations/{$organization->id}/sync")
            ->assertOk();

        Queue::assertNothingPushed();

        $this->actingAs($organization->user)
            ->postJson("/api/organizations/{$organization->id}/sync", ['force' => true])
            ->assertOk();

        Queue::assertPushed(SyncOrganizationReviewsJob::class);
    }
}
