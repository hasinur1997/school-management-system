<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'session_id' => AcademicSession::factory(),
            'class_id' => SchoolClass::factory()->state(['branch_id' => $branch]),
            'type' => fake()->randomElement(ExamType::cases()),
            'name' => fake()->randomElement(['First Semester', 'Second Semester', 'Final']).' '.fake()->year(),
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
            'status' => ExamStatus::Upcoming,
        ];
    }

    /**
     * A published exam (frozen against further edits).
     */
    public function published(): static
    {
        return $this->state(['status' => ExamStatus::Published]);
    }
}
