<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'value' => fake()->randomFloat(2, 1000, 100000),
            'purchase_date' => fake()->optional()->dateTimeBetween('-3 years', 'now')?->format('Y-m-d'),
            'status' => AssetStatus::InUse,
            'created_by' => User::factory(),
        ];
    }
}
