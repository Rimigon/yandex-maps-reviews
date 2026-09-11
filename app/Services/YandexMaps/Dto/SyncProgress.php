<?php

namespace App\Services\YandexMaps\Dto;

/**
 * Прогресс фоновой выгрузки: сколько страниц отзывов уже пройдено.
 */
final class SyncProgress
{
    public function __construct(
        public readonly int $pagesDone,
        public readonly int $pagesTotal,
        public readonly int $reviewsStored,
        public readonly int $reviewsTotal,
    ) {}
}
