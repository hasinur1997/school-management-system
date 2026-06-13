<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * teacher_id is an unconstrained integer until Task 2.1 adds the teachers
     * table and FK; tests override it with a real teacher id once that lands.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => fake()->numberBetween(1, 1000),
            'session_id' => AcademicSession::factory(),
            'class_id' => SchoolClass::factory(),
            'section_id' => null,
            'subject_id' => null,
        ];
    }
}
