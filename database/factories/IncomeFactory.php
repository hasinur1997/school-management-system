<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    /**
     * Define the model's default state. Manual income by default (payment_id
     * null → not system-generated).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'category_id' => Category::factory()->state(['type' => CategoryType::Income]),
            'payment_id' => null,
            'title' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
