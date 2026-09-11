<?php

namespace App\Services\Sync;

use App\Models\Organization;
use App\Services\YandexMaps\Browser\BrowserCollector;
use App\Services\YandexMaps\Dto\OrganizationCard;
use App\Services\YandexMaps\Dto\ReviewPage;
use App\Services\YandexMaps\Dto\SyncProgress;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use App\Services\YandexMaps\YandexMapsClient;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Выгрузка отзывов по одной организации.
 *
 * Связывает разбор внешнего источника с базой: обновляет карточку, пишет
 * отзывы, двигает прогресс (по нему фронт рисует полосу) и фиксирует снимок
 * «было -> стало». Если прямой путь упёрся в защиту Яндекса, а запасной путь
 * включён, выгрузка повторяется через headless-браузер.
 */
final class OrganizationSyncService
{
    public function __construct(
        private readonly YandexMapsClient $client,
        private readonly BrowserCollector $browser,
        private readonly ReviewRepository $reviews,
        private readonly SnapshotRepository $snapshots,
        private readonly LoggerInterface $logger,
    ) {}

    public function sync(Organization $organization): SyncResult
    {
        $organization->markRunning();

        try {
            return $this->syncDirectly($organization);
        } catch (Throwable $exception) {
            if (! $this->browser->enabled()) {
                $this->fail($organization, $exception);

                throw $exception;
            }

            $this->logger->warning('Прямой парсинг не удался, перехожу на headless-браузер', [
                'organization_id' => $organization->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            return $this->syncViaBrowser($organization);
        } catch (Throwable $fallbackException) {
            $this->fail($organization, $fallbackException);

            throw $fallbackException;
        }
    }

    private function syncDirectly(Organization $organization): SyncResult
    {
        $session = $this->client->openSession(
            $organization->normalized_url,
            $organization->business_id,
        );

        return $this->persist(
            organization: $organization,
            card: $session->card(),
            pages: $this->client->streamReviews($session),
        );
    }

    private function syncViaBrowser(Organization $organization): SyncResult
    {
        $collected = $this->browser->collect($organization->normalized_url);

        return $this->persist(
            organization: $organization,
            card: $collected->page->card,
            pages: $collected->pages,
        );
    }

    /**
     * Общая часть обоих путей: обновить карточку, сохранить отзывы,
     * показать прогресс и записать снимок.
     *
     * @param  iterable<ReviewPage>  $pages
     */
    private function persist(Organization $organization, OrganizationCard $card, iterable $pages): SyncResult
    {
        $this->guardAgainstDuplicate($organization, $card->businessId);

        $before = $this->snapshotValues($organization);
        $organization->applyCard($card);

        $saved = new ReviewSaveResult;
        $pagesProcessed = 0;
        $stored = $this->reviews->countFor($organization);
        $reviewsOnCard = $card->reviewsTotal;

        foreach ($pages as $page) {
            $saved = $saved->plus($this->reviews->storePage($organization, $page->reviews));

            $pagesProcessed = max($pagesProcessed, $page->page);
            $reviewsOnCard = $page->totalCount > 0 ? $page->totalCount : $reviewsOnCard;
            $stored = $this->reviews->countFor($organization);

            $organization->updateProgress(new SyncProgress(
                pagesDone: $pagesProcessed,
                pagesTotal: min(max($page->lastPage, 1), $this->client->maxPages()),
                reviewsStored: $stored,
                reviewsTotal: $reviewsOnCard,
            ));
        }

        $organization->markCompleted($stored);
        $this->snapshots->capture($organization, $before, $saved);

        $this->logger->info('Отзывы выгружены', [
            'organization_id' => $organization->getKey(),
            'pages' => $pagesProcessed,
            'stored' => $stored,
            ...$saved->toArray(),
        ]);

        return new SyncResult(
            organization: $organization,
            pagesProcessed: $pagesProcessed,
            reviewsOnCard: $reviewsOnCard,
            reviewsStored: $stored,
            reviews: $saved,
        );
    }

    private function fail(Organization $organization, Throwable $exception): void
    {
        $organization->markFailed($exception->getMessage());

        $this->logger->error('Выгрузка отзывов не удалась', [
            'organization_id' => $organization->getKey(),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Одна и та же организация может быть добавлена дважды разными ссылками
     * (короткой и обычной). Такое ловим до записи в базу.
     */
    private function guardAgainstDuplicate(Organization $organization, string $businessId): void
    {
        $duplicate = Organization::query()
            ->where('user_id', $organization->user_id)
            ->where('business_id', $businessId)
            ->whereKeyNot($organization->getKey())
            ->exists();

        if ($duplicate) {
            throw new YandexMapsException(
                'Эта организация уже добавлена в аккаунт — вторую карточку создавать не нужно.'
            );
        }
    }

    /**
     * @return array{rating: float|string|null, ratings_total: int, reviews_total: int, reviews_parsed: int}
     */
    private function snapshotValues(Organization $organization): array
    {
        return [
            'rating' => $organization->rating,
            'ratings_total' => (int) $organization->ratings_total,
            'reviews_total' => (int) $organization->reviews_total,
            'reviews_parsed' => (int) $organization->reviews_parsed,
        ];
    }
}
