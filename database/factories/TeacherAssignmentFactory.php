<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
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
     * teacher_id references a real teacher since Task 2.1 added the FK; tests
     * may override it with a specific teacher id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'session_id' => AcademicSession::factory(),
            'class_id' => SchoolClass::factory(),
            'section_id' => null,
            'subject_id' => null,
        ];
    }
}
