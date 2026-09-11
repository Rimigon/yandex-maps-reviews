<?php

namespace App\Services\Sync;

use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexMaps\Dto\ReviewData;

/**
 * Сохранение отзывов.
 *
 * Повторный парсинг не должен плодить дубли, поэтому отзывы пишутся через
 * upsert по паре (организация, reviewId из Яндекса), а изменённые записи
 * определяются по отпечатку содержимого.
 */
final class ReviewRepository
{
    /**
     * @param  list<ReviewData>  $reviews
     */
    public function storePage(Organization $organization, array $reviews): ReviewSaveResult
    {
        if ($reviews === []) {
            return new ReviewSaveResult;
        }

        $now = now()->toDateTimeString();

        $rows = array_map(static fn (ReviewData $review): array => [
            'organization_id' => $organization->getKey(),
            ...$review->toRow(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $reviews);

        $existing = Review::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('external_id', array_column($rows, 'external_id'))
            ->pluck('content_hash', 'external_id')
            ->all();

        $result = new ReviewSaveResult;

        foreach ($rows as $row) {
            $knownHash = $existing[$row['external_id']] ?? null;

            $result = $result->plus(match (true) {
                $knownHash === null => new ReviewSaveResult(created: 1),
                $knownHash !== $row['content_hash'] => new ReviewSaveResult(updated: 1),
                default => new ReviewSaveResult(unchanged: 1),
            });
        }

        Review::upsert(
            $rows,
            uniqueBy: ['organization_id', 'external_id'],
            update: [
                'author_name', 'author_avatar_url', 'rating', 'text', 'business_comment',
                'likes', 'dislikes', 'is_pinned', 'published_at', 'source_updated_at',
                'content_hash', 'updated_at',
            ],
        );

        return $result;
    }

    public function countFor(Organization $organization): int
    {
        return $organization->reviews()->count();
    }
}
