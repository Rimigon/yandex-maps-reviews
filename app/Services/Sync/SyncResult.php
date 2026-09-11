<?php

namespace App\Services\Sync;

use App\Models\Organization;

/**
 * Результат выгрузки отзывов — возвращается джобой и консольной командой.
 */
final class SyncResult
{
    public function __construct(
        public readonly Organization $organization,
        public readonly int $pagesProcessed,
        public readonly int $reviewsOnCard,
        public readonly int $reviewsStored,
        public readonly ReviewSaveResult $reviews,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization->getKey(),
            'title' => $this->organization->title,
            'pages_processed' => $this->pagesProcessed,
            'reviews_on_card' => $this->reviewsOnCard,
            'reviews_stored' => $this->reviewsStored,
            'reviews_created' => $this->reviews->created,
            'reviews_updated' => $this->reviews->updated,
            'reviews_unchanged' => $this->reviews->unchanged,
        ];
    }
}
