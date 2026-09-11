<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();
        $rating = fake()->numberBetween(1, 5);
        $author = fake()->name();
        $sourceUpdatedAt = fake()->dateTimeBetween('-2 years');

        return [
            'organization_id' => Organization::factory(),
            'external_id' => Str::random(32),
            'author_name' => $author,
            'author_avatar_url' => null,
            'rating' => $rating,
            'text' => $text,
            'business_comment' => null,
            'likes' => fake()->numberBetween(0, 50),
            'dislikes' => 0,
            'is_pinned' => false,
            'source_updated_at' => $sourceUpdatedAt,
            'content_hash' => sha1("{$author}|{$rating}|{$text}"),
        ];
    }
}
