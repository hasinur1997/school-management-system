<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResult>
 */
class ExamResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'enrollment_id' => Enrollment::factory(),
            'total_marks' => fake()->randomFloat(2, 100, 500),
            'gpa' => fake()->randomFloat(2, 1, 5),
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
