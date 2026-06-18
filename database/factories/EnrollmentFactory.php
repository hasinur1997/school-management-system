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
     * Class and section are kept coherent: class_id is derived from the
     * resolved section. Both are lazy so that callers overriding `section_id`
     * and/or `class_id` (the common case) never trigger a throwaway section +
     * class — eager creation here previously leaked stray rows whose branch_id
     * could be re-stamped by the authenticated user, colliding with explicitly
     * seeded classes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'session_id' => AcademicSession::factory(),
            'section_id' => Section::factory(),
            'class_id' => fn (array $attributes): int => Section::findOrFail($attributes['section_id'])->class_id,
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
