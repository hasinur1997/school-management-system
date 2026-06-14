<?php

namespace Database\Factories;

use App\Models\GradingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingScale>
 */
class GradingScaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade' => fake()->randomLetter(),
            'min_marks' => 0,
            'max_marks' => 100,
            'grade_point' => fake()->randomFloat(2, 0, 5),
            'is_fail' => false,
        ];
    }
}
