<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mark>
 */
class MarkFactory extends Factory
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
            'subject_id' => Subject::factory(),
            'obtained_marks' => fake()->randomFloat(2, 33, 100),
            'grade' => 'A',
            'grade_point' => 4.00,
            'entered_by' => User::factory(),
        ];
    }
}
