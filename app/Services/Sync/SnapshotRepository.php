<?php

namespace App\Services\Sync;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;

/**
 * Снимки состояния карточки: после каждой выгрузки сохраняется рейтинг,
 * счётчики и дельта по отзывам, чтобы было видно «было -> стало».
 */
final class SnapshotRepository
{
    /**
     * @param  array{rating: float|string|null, ratings_total: int, reviews_total: int, reviews_parsed: int}  $before
     */
    public function capture(Organization $organization, array $before, ReviewSaveResult $reviews): OrganizationSnapshot
    {
        $organization->refresh();

        return $organization->snapshots()->create([
            'rating' => $organization->rating,
            'ratings_total' => $organization->ratings_total,
            'reviews_total' => $organization->reviews_total,
            'reviews_parsed' => $organization->reviews_parsed,
            'changes' => [
                'reviews_added' => $reviews->created,
                'reviews_updated' => $reviews->updated,
                'rating' => $this->delta($before['rating'], $organization->rating),
                'ratings_total' => $this->delta($before['ratings_total'], $organization->ratings_total),
                'reviews_total' => $this->delta($before['reviews_total'], $organization->reviews_total),
                'reviews_parsed' => $this->delta($before['reviews_parsed'], $organization->reviews_parsed),
            ],
            'captured_at' => now(),
        ]);
    }

    /**
     * @return array{from: float|int|string|null, to: float|int|string|null, diff: float|int|null}
     */
    private function delta(float|int|string|null $from, float|int|string|null $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            // У первой выгрузки сравнивать не с чем: показывать рост «с нуля»
            // как изменение рейтинга нельзя.
            'diff' => $from === null ? null : round((float) $to - (float) $from, 2),
        ];
    }
}
