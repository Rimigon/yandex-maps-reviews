<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\Sync\OrganizationSyncService;
use App\Services\YandexMaps\Exceptions\BlockedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Запасной путь: когда прямой парсинг упирается в защиту Яндекса, отзывы
 * собирает headless-браузер. Здесь вместо реального браузера — скрипт-двойник,
 * он отдаёт тот же JSON, что и collect-reviews.mjs.
 */
class BrowserFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('yandex-maps.throttle_ms', ['min' => 0, 'max' => 0]);
        config()->set('yandex-maps.retry.sleep_ms', 0);
        config()->set('yandex-maps.retry.times', 1);
        config()->set('yandex-maps.browser_fallback', [
            'enabled' => true,
            'command' => PHP_BINARY,
            'script' => __DIR__.'/../Fixtures/fake-browser-collector.php',
            'timeout' => 60,
        ]);
    }

    public function test_при_блокировке_яндекса_отзывы_собирает_браузер(): void
    {
        $organization = Organization::factory()->for(User::factory())->create([
            'normalized_url' => 'https://yandex.ru/maps/org/test-coffee/69353267050',
            'source_url' => 'https://yandex.ru/maps/org/test-coffee/69353267050',
            'business_id' => '69353267050',
        ]);

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response('Forbidden', 403),
            'yandex.ru/maps/api/business/fetchReviews*' => Http::response('Forbidden', 403),
        ]);

        $result = app(OrganizationSyncService::class)->sync($organization);
        $organization->refresh();

        $this->assertSame(SyncStatus::Completed, $organization->sync_status);
        $this->assertSame(3, $result->reviewsStored);
        $this->assertSame('Тестовая кофейня', $organization->title);
        $this->assertSame(638, $organization->reviews_total);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'browser-1',
            'text' => 'Отзыв, собранный браузером',
        ]);
    }

    public function test_без_включённого_запасного_пути_ошибка_остаётся_ошибкой(): void
    {
        config()->set('yandex-maps.browser_fallback.enabled', false);

        $organization = Organization::factory()->for(User::factory())->create([
            'normalized_url' => 'https://yandex.ru/maps/org/test-coffee/69353267050',
            'business_id' => '69353267050',
        ]);

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response('Forbidden', 403),
        ]);

        try {
            app(OrganizationSyncService::class)->sync($organization);
            $this->fail('Ожидалась ошибка блокировки');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(BlockedException::class, $exception);
        }

        $this->assertSame(SyncStatus::Failed, $organization->refresh()->sync_status);
        $this->assertSame(0, $organization->reviews()->count());
    }
}
