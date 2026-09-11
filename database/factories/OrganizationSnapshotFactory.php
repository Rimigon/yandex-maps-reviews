<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSnapshot>
 */
class OrganizationSnapshotFactory extends Factory
{
    protected $model = OrganizationSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_total' => fake()->numberBetween(10, 5000),
            'reviews_total' => fake()->numberBetween(10, 600),
            'reviews_parsed' => fake()->numberBetween(0, 600),
            'changes' => [],
            'captured_at' => now(),
        ];
    }
}
