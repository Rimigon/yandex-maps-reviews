<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Sync\OrganizationSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Фоновая выгрузка отзывов по организации.
 *
 * Один парсинг — это до 12 запросов к Яндексу с паузами, поэтому в
 * HTTP-запросе такое держать нельзя: организация ставится в очередь, фронт
 * опрашивает её и показывает прогресс. При сбое джоба повторяется с
 * нарастающей паузой, а каждая попытка открывает новую сессию, то есть
 * заходит с другим User-Agent и прокси.
 */
class SyncOrganizationReviewsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> паузы между попытками, секунды */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $organizationId,
    ) {
        $this->onQueue((string) config('yandex-maps.queue', 'default'));
    }

    public function handle(OrganizationSyncService $sync): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return; // организация удалена, пока джоба ждала очереди
        }

        $sync->sync($organization);
    }

    /**
     * Последняя попытка тоже не удалась: фиксируем причину в организации,
     * чтобы она была видна в интерфейсе, а не только в логах.
     */
    public function failed(?Throwable $exception): void
    {
        $organization = Organization::find($this->organizationId);

        $organization?->markFailed($exception?->getMessage() ?? 'Выгрузка не удалась');
    }
}
