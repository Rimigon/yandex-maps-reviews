<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'author' => [
                'name' => $this->author_name ?? 'Пользователь Яндекс.Карт',
                'avatar' => $this->avatarUrl(),
            ],
            'rating' => $this->rating,
            'text' => $this->text,
            'business_comment' => $this->business_comment,
            'likes' => $this->likes,
            'dislikes' => $this->dislikes,
            'is_pinned' => $this->is_pinned,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
