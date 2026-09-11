<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\Sync\OrganizationSyncService;
use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OrganizationSyncTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS_ID = '69353267050';

    private const PAGE_URL = 'https://yandex.ru/maps/org/test-coffee/69353267050/reviews/';

    protected function setUp(): void
    {
        parent::setUp();

        // Паузы между запросами нужны в бою, в тестах только замедляют прогон.
        config()->set('yandex-maps.throttle_ms', ['min' => 0, 'max' => 0]);
        config()->set('yandex-maps.retry.sleep_ms', 0);
    }

    public function test_выгружает_карточку_и_все_страницы_отзывов(): void
    {
        $organization = $this->organization();
        $this->fakeYandex(totalReviews: 60, perPage: 50);

        $result = app(OrganizationSyncService::class)->sync($organization);

        $organization->refresh();

        $this->assertSame(SyncStatus::Completed, $organization->sync_status);
        $this->assertSame('Тестовая кофейня', $organization->title);
        $this->assertSame('4.90', $organization->rating);
        $this->assertSame(1231, $organization->ratings_total);
        $this->assertSame(638, $organization->reviews_total);
        $this->assertSame(60, $organization->reviews_parsed);
        $this->assertSame(2, $result->pagesProcessed);

        $this->assertDatabaseCount('reviews', 60);
        $this->assertSame(60, $result->reviews->created);
        $this->assertNotNull($organization->last_synced_at);
    }

    public function test_повторная_выгрузка_не_плодит_дубли_и_обновляет_изменившийся_отзыв(): void
    {
        $organization = $this->organization();
        $edited = false;

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response($this->pageHtml(), 200),
            'yandex.ru/maps/api/business/fetchReviews*' => function (Request $request) use (&$edited) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response(
                    $this->reviewsPayload($page, edits: $edited && $page === 1 ? [1 => 'Отзыв отредактирован'] : []),
                    200,
                );
            },
        ]);

        app(OrganizationSyncService::class)->sync($organization);

        $edited = true;
        $result = app(OrganizationSyncService::class)->sync($organization->refresh());

        $this->assertDatabaseCount('reviews', 60);
        $this->assertSame(0, $result->reviews->created);
        $this->assertSame(1, $result->reviews->updated);
        $this->assertSame(59, $result->reviews->unchanged);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-page1-1',
            'text' => 'Отзыв отредактирован',
        ]);

        $snapshot = $organization->snapshots()->latest('id')->first();

        $this->assertSame(0, $snapshot->changes['reviews_added']);
        $this->assertSame(1, $snapshot->changes['reviews_updated']);
        $this->assertSame(60, $snapshot->changes['reviews_parsed']['to']);
    }

    public function test_в_первом_снимке_нет_дельты_рейтинга_сравнивать_не_с_чем(): void
    {
        // Так выглядит организация сразу после добавления: рейтинга ещё нет.
        $organization = $this->organization();
        $organization->forceFill(['rating' => null, 'ratings_total' => 0, 'reviews_total' => 0])->save();

        $this->fakeYandex(totalReviews: 60);

        app(OrganizationSyncService::class)->sync($organization);

        $changes = $organization->snapshots()->first()->changes;

        $this->assertNull($changes['rating']['from']);
        $this->assertSame('4.90', $changes['rating']['to']);
        $this->assertNull($changes['rating']['diff']);
        $this->assertSame(60, $changes['reviews_added']);
    }

    public function test_снимки_показывают_что_изменилось_между_выгрузками(): void
    {
        $organization = $this->organization();
        $pageRequests = 0;

        Http::fake([
            'yandex.ru/maps/org/*' => function () use (&$pageRequests) {
                $pageRequests++;

                return Http::response(
                    str_replace('"reviewCount":638', '"reviewCount":'.($pageRequests > 1 ? 639 : 638), $this->pageHtml()),
                    200,
                );
            },
            'yandex.ru/maps/api/business/fetchReviews*' => fn (Request $request) => Http::response(
                $this->reviewsPayload((int) ($request->data()['page'] ?? 1)),
                200,
            ),
        ]);

        app(OrganizationSyncService::class)->sync($organization);
        app(OrganizationSyncService::class)->sync($organization->refresh());

        $changes = $organization->snapshots()->latest('id')->first()->changes;

        $this->assertSame(638, $changes['reviews_total']['from']);
        $this->assertSame(639, $changes['reviews_total']['to']);
        $this->assertSame(1, $changes['reviews_total']['diff']);
    }

    public function test_поломка_разметки_переводит_выгрузку_в_ошибку_с_понятным_текстом(): void
    {
        $organization = $this->organization();

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response('<html><body>новая вёрстка</body></html>', 200),
        ]);

        try {
            app(OrganizationSyncService::class)->sync($organization);
            $this->fail('Ожидалась ошибка разбора страницы');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('state-view', $exception->getMessage());
        }

        $organization->refresh();

        $this->assertSame(SyncStatus::Failed, $organization->sync_status);
        $this->assertStringContainsString('state-view', (string) $organization->sync_error);
    }

    public function test_обновлённый_csrf_токен_подхватывается_и_запрос_повторяется(): void
    {
        $organization = $this->organization();
        $attempts = 0;

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response($this->pageHtml(), 200),
            'yandex.ru/maps/api/business/fetchReviews*' => function (Request $request) use (&$attempts) {
                $attempts++;

                // Первый запрос Яндекс встречает новым токеном вместо данных.
                return $attempts === 1
                    ? Http::response(['csrfToken' => 'fresh-token:123'], 200)
                    : Http::response($this->reviewsPayload((int) ($request->data()['page'] ?? 1)), 200);
            },
        ]);

        $result = app(OrganizationSyncService::class)->sync($organization);

        $this->assertGreaterThanOrEqual(2, $attempts);
        $this->assertSame(60, $result->reviews->created);
        $this->assertSame(SyncStatus::Completed, $organization->refresh()->sync_status);
    }

    public function test_в_запрос_к_яндексу_не_попадают_пустые_параметры(): void
    {
        $this->fakeYandex(totalReviews: 60);

        app(OrganizationSyncService::class)->sync($this->organization());

        // Пустой host_config ломает запрос: Яндекс отвечает 400 и отзывов не даёт.
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'fetchReviews')) {
                return false;
            }

            $query = $request->data();

            return ! array_key_exists('host_config', $query)
                && ! array_key_exists('sessionId', array_filter($query, static fn ($value) => $value === ''))
                && isset($query['s'], $query['csrfToken'], $query['businessId']);
        });
    }

    public function test_карточка_без_отзывов_обрабатывается_как_пустая_а_не_как_ошибка(): void
    {
        $organization = $this->organization();

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                str_replace(['"reviewCount":638', '"ratingCount":1231'], ['"reviewCount":0', '"ratingCount":0'], $this->pageHtml()),
                200,
            ),
            'yandex.ru/maps/api/business/fetchReviews*' => Http::response([
                'data' => [
                    'params' => ['page' => 1, 'limit' => 50, 'count' => 0, 'totalPages' => 1],
                    'reviews' => [],
                ],
            ], 200),
        ]);

        $result = app(OrganizationSyncService::class)->sync($organization);

        $this->assertSame(SyncStatus::Completed, $organization->refresh()->sync_status);
        $this->assertSame(0, $result->reviewsStored);
        $this->assertSame(0, $result->reviews->total());
        $this->assertSame(1, $result->pagesProcessed);
        $this->assertNull($organization->sync_error);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_недоступная_страница_даёт_понятную_ошибку(): void
    {
        $organization = $this->organization();

        Http::fake(fn () => throw new ConnectionException('Could not resolve host: yandex.ru'));

        try {
            app(OrganizationSyncService::class)->sync($organization);
            $this->fail('Ожидалась ошибка соединения');
        } catch (YandexMapsException $exception) {
            $this->assertStringContainsString('Не удалось соединиться', $exception->getMessage());
            $this->assertStringNotContainsString('Could not resolve', $exception->getMessage());
        }

        $organization->refresh();

        $this->assertSame(SyncStatus::Failed, $organization->sync_status);
        $this->assertNotNull($organization->sync_error);
    }

    public function test_неожиданный_ответ_яндекса_попадает_в_лог_с_телом(): void
    {
        // Требование №1 из ТЗ: поломка должна быть видна. Одного текста ошибки
        // мало, поэтому в лог пишется ещё и то, что реально пришло.
        Log::spy();

        $organization = $this->organization();

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response($this->pageHtml(), 200),
            'yandex.ru/maps/api/business/fetchReviews*' => Http::response(
                ['unexpected' => 'структура ответа изменилась'],
                200,
            ),
        ]);

        try {
            app(OrganizationSyncService::class)->sync($organization);
            $this->fail('Ожидалась ошибка разбора');
        } catch (LayoutChangedException) {
            // ожидаемо
        }

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'Яндекс вернул ответ неожиданной структуры'
                && ($context['response_excerpt'] ?? null) !== null
                && str_contains((string) $context['response_excerpt'], 'структура ответа изменилась'))
            ->once();
    }

    public function test_блокировка_яндексом_не_выдаётся_за_пустой_список_отзывов(): void
    {
        $organization = $this->organization();

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response($this->pageHtml(), 200),
            'yandex.ru/maps/api/business/fetchReviews*' => Http::response('Forbidden', 403),
        ]);

        $this->expectException(BlockedException::class);

        try {
            app(OrganizationSyncService::class)->sync($organization);
        } finally {
            $this->assertSame(0, $organization->refresh()->reviews()->count());
        }
    }

    private function organization(): Organization
    {
        return Organization::factory()->for(User::factory())->create([
            'normalized_url' => 'https://yandex.ru/maps/org/test-coffee/'.self::BUSINESS_ID,
            'source_url' => self::PAGE_URL,
            'business_id' => self::BUSINESS_ID,
        ]);
    }

    /**
     * @param  array<int, string|null>  $edits
     */
    private function reviewsPayload(int $page, int $total = 60, array $edits = []): array
    {
        $perPage = 50;
        $from = ($page - 1) * $perPage + 1;
        $to = min($total, $from + $perPage - 1);
        $reviews = [];

        for ($index = $from; $index <= $to; $index++) {
            $reviews[] = [
                'reviewId' => "review-page{$page}-".($index - $from + 1),
                'author' => ['name' => 'Автор '.$index],
                'text' => $edits[$index - $from + 1] ?? "Отзыв номер {$index}",
                'rating' => ($index % 5) + 1,
                'updatedTime' => '2026-08-04T13:07:09.580Z',
                'reactions' => ['likes' => $index % 7, 'dislikes' => 0],
            ];
        }

        return [
            'data' => [
                'params' => [
                    'page' => $page,
                    'limit' => $perPage,
                    'count' => $total,
                    'totalPages' => (int) ceil($total / $perPage),
                ],
                'reviews' => $reviews,
            ],
        ];
    }

    private function pageHtml(): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/org-page.html');
    }

    private function fakeYandex(int $totalReviews, int $perPage = 50): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response($this->pageHtml(), 200),
            'yandex.ru/maps/api/business/fetchReviews*' => fn (Request $request) => Http::response(
                $this->reviewsPayload((int) ($request->data()['page'] ?? 1), $totalReviews),
                200,
            ),
        ]);
    }
}
