<?php

namespace Database\Factories;

use App\Models\AnnualResult;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualResult>
 */
class AnnualResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'first_semester_gpa' => fake()->randomFloat(2, 1, 5),
            'second_semester_gpa' => fake()->randomFloat(2, 1, 5),
            'final_exam_gpa' => fake()->randomFloat(2, 1, 5),
            'annual_gpa' => fake()->randomFloat(2, 1, 5),
            'grade' => 'A',
            'is_passed' => true,
            'published_at' => null,
        ];
    }

    /**
     * Indicate the result has been published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()]);
    }
}
