<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state. The exam is created with no classes;
     * attach them with {@see forClass()} / {@see forClasses()} or set
     * {@see allClasses()}.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'session_id' => AcademicSession::factory(),
            'type' => fake()->randomElement(ExamType::cases()),
            'name' => fake()->randomElement(['First Semester', 'Second Semester', 'Final']).' '.fake()->year(),
            'all_classes' => false,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
            'status' => ExamStatus::Upcoming,
        ];
    }

    /**
     * Target a single class, stamping the exam's branch from it and attaching it
     * to the exam_class pivot.
     */
    public function forClass(SchoolClass $class): static
    {
        return $this->state(['branch_id' => $class->branch_id])
            ->afterCreating(fn (Exam $exam) => $exam->classes()->syncWithoutDetaching([$class->id]));
    }

    /**
     * Target several classes (all in the same branch).
     *
     * @param  Collection<int, SchoolClass>|array<int, SchoolClass>  $classes
     */
    public function forClasses(Collection|array $classes): static
    {
        $classes = Collection::wrap($classes);

        return $this->state(['branch_id' => $classes->first()?->branch_id])
            ->afterCreating(fn (Exam $exam) => $exam->classes()->syncWithoutDetaching($classes->modelKeys()));
    }

    /**
     * Target every class in the exam's branch (no pivot rows; resolved
     * dynamically via Exam::classIds()).
     */
    public function allClasses(): static
    {
        return $this->state(['all_classes' => true]);
    }

    /**
     * A published exam (frozen against further edits).
     */
    public function published(): static
    {
        return $this->state(['status' => ExamStatus::Published]);
    }
}
