<?php

namespace App\Http\Resources;

use App\Models\OrganizationSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationSnapshot
 */
class OrganizationSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'ratings_total' => $this->ratings_total,
            'reviews_total' => $this->reviews_total,
            'reviews_parsed' => $this->reviews_parsed,
            'changes' => $this->changes,
            'captured_at' => $this->captured_at?->toIso8601String(),
        ];
    }
}
