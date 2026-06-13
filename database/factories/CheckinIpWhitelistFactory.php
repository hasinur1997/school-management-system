<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CheckinIpWhitelist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckinIpWhitelist>
 */
class CheckinIpWhitelistFactory extends Factory
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
            'ip_address' => fake()->unique()->ipv4(),
            'label' => fake()->words(2, true),
            'is_active' => true,
        ];
    }

    /**
     * An inactive entry.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
