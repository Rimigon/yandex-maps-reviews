<?php

namespace App\Services\YandexMaps\Dto;

/**
 * Одна страница отзывов из внутреннего API Яндекс.Карт.
 */
final class ReviewPage
{
    /**
     * @param  list<ReviewData>  $reviews
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalCount,
        public readonly int $lastPage,
        public readonly array $reviews,
    ) {}

    public function isEmpty(): bool
    {
        return $this->reviews === [];
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->lastPage;
    }
}
