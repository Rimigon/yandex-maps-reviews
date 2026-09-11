<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'title' => $this->title,
            'address' => $this->address,
            'url' => $this->normalized_url,
            'source_url' => $this->source_url,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'ratings_total' => $this->ratings_total,
            'reviews_total' => $this->reviews_total,
            'reviews_parsed' => $this->reviews_parsed,
            'sync' => [
                'status' => $this->sync_status->value,
                'status_label' => $this->sync_status->label(),
                'in_progress' => $this->sync_status->isInProgress(),
                'pages_done' => $this->sync_pages_done,
                'pages_total' => $this->sync_pages_total,
                'progress' => $this->progressPercent(),
                'error' => $this->sync_error,
            ],
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
