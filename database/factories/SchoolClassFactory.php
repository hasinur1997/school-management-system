<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(1, 12);

        return [
            'branch_id' => Branch::factory(),
            'name' => "Class {$level}",
            'numeric_level' => $level,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the class is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
