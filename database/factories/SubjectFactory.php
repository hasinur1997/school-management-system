<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'name' => fake()->unique()->randomElement([
                'Bangla', 'English', 'Mathematics', 'Science', 'Religion',
                'ICT', 'Social Science', 'Physics', 'Chemistry', 'Biology',
                'Higher Mathematics', 'Geography', 'History', 'Arabic',
                'Drawing', 'Physical Education', 'Agriculture', 'Economics',
                'Civics', 'Accounting',
            ]),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'full_marks' => 100,
            'pass_marks' => 33,
        ];
    }
}
