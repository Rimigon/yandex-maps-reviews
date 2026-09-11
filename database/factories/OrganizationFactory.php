<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessId = (string) fake()->unique()->numberBetween(1000000000, 9999999999);
        $url = "https://yandex.ru/maps/org/test-company/{$businessId}";

        return [
            'user_id' => User::factory(),
            'source_url' => $url,
            'normalized_url' => $url,
            'business_id' => $businessId,
            'title' => fake()->company(),
            'address' => fake()->address(),
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_total' => fake()->numberBetween(10, 5000),
            'reviews_total' => fake()->numberBetween(10, 600),
            'reviews_parsed' => 0,
            'sync_status' => SyncStatus::Idle,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'sync_status' => SyncStatus::Completed,
            'last_synced_at' => now(),
        ]);
    }
}
