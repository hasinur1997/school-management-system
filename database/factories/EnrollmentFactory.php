<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Class and section are kept coherent: a section is built first and its
     * parent class supplies class_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $section = Section::factory()->create();

        return [
            'student_id' => Student::factory(),
            'session_id' => AcademicSession::factory(),
            'class_id' => $section->class_id,
            'section_id' => $section->id,
            'roll_no' => fake()->unique()->numberBetween(1, 9999),
            'status' => EnrollmentStatus::Active,
        ];
    }

    /**
     * Place the enrollment in the current academic session.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_id' => AcademicSession::factory()->current(),
        ]);
    }
}
