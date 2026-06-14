<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Mark;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the per-exam result engine: the GPA/grade computation, the repeatable
 * generation into exam_results, and the publication freeze. Generation is
 * idempotent until publication; once published the exam is frozen (409).
 */
class ResultService
{
    public function __construct(private readonly GradeResolver $grades) {}

    /**
     * (Re)generate exam_results for every active enrollment of the exam's class
     * that has a complete set of marks. Enrollments missing a mark for any
     * subject of the class are skipped and reported.
     *
     * Per-exam GPA = average of the subjects' grade points (2 dp, round half
     * up); the overall grade is the band the GPA maps to, but a failing grade
     * overrides it (and clears is_passed) whenever any subject failed.
     *
     * @return array{generated: int, skipped: list<array{enrollment_id: int, missing_subjects: list<string>}>}
     */
    public function generateExamResults(Exam $exam): array
    {
        abort_if($exam->status === ExamStatus::Published, 409, 'Results are frozen for published exams');

        $marksByEnrollment = Mark::query()
            ->where('exam_id', $exam->id)
            ->get()
            ->groupBy('enrollment_id');

        abort_if($marksByEnrollment->isEmpty(), 422, 'No marks entered for this exam');

        /** @var Collection<int, Subject> $subjects */
        $subjects = Subject::query()->where('class_id', $exam->class_id)->get();

        $enrollments = Enrollment::query()
            ->where('class_id', $exam->class_id)
            ->where('session_id', $exam->session_id)
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $failGrades = $this->grades->all()
            ->where('is_fail', true)
            ->pluck('grade')
            ->all();

        $rows = [];
        $skipped = [];
        $now = now();

        foreach ($enrollments as $enrollment) {
            /** @var Collection<int, Mark> $marks */
            $marks = $marksByEnrollment->get($enrollment->id) ?? collect();
            $markedSubjectIds = $marks->pluck('subject_id')->all();

            $missing = $subjects
                ->reject(fn (Subject $subject): bool => in_array($subject->id, $markedSubjectIds, true))
                ->pluck('name')
                ->values()
                ->all();

            if ($missing !== []) {
                $skipped[] = [
                    'enrollment_id' => $enrollment->id,
                    'missing_subjects' => $missing,
                ];

                continue;
            }

            $rows[] = $this->computeRow($exam, $enrollment->id, $marks, $failGrades, $now);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ExamResult::upsert(
                $chunk,
                ['exam_id', 'enrollment_id'],
                ['total_marks', 'gpa', 'grade', 'is_passed', 'updated_at'],
            );
        }

        return [
            'generated' => count($rows),
            'skipped' => $skipped,
        ];
    }

    /**
     * Freeze the exam's results: stamp published_at on every row and move the
     * exam to published status, in one transaction. Re-publishing → 409.
     *
     * @return array{published: int}
     */
    public function publishExamResults(Exam $exam): array
    {
        abort_if($exam->status === ExamStatus::Published, 409, 'Results are already published');

        return DB::transaction(function () use ($exam): array {
            $published = ExamResult::query()
                ->where('exam_id', $exam->id)
                ->update(['published_at' => now()]);

            $exam->update(['status' => ExamStatus::Published]);

            return ['published' => $published];
        });
    }

    /**
     * Browse an exam's results in the caller's branch (scope is automatic via
     * the enrollment chain), filtered by section/pass status, ordered by GPA
     * descending. The enrollment + student are eager loaded for the resource.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listExamResults(Exam $exam, array $filters, int $perPage): LengthAwarePaginator
    {
        return ExamResult::query()
            ->where('exam_id', $exam->id)
            ->with('enrollment.student')
            ->when(
                isset($filters['section_id']),
                fn (Builder $query) => $query->whereHas('enrollment', fn (Builder $e) => $e->where('section_id', $filters['section_id'])),
            )
            ->when(
                isset($filters['is_passed']),
                fn (Builder $query) => $query->where('is_passed', $filters['is_passed']),
            )
            ->orderByDesc('gpa')
            ->paginate($perPage);
    }

    /**
     * Build one exam_results upsert row from a complete set of subject marks.
     *
     * @param  Collection<int, Mark>  $marks
     * @param  list<string>  $failGrades
     * @return array<string, mixed>
     */
    private function computeRow(Exam $exam, int $enrollmentId, Collection $marks, array $failGrades, \DateTimeInterface $now): array
    {
        $total = $marks->sum(fn (Mark $mark): float => (float) $mark->obtained_marks);
        $gpa = round($marks->avg(fn (Mark $mark): float => (float) $mark->grade_point), 2);

        $failed = $marks->contains(fn (Mark $mark): bool => in_array($mark->grade, $failGrades, true));

        $grade = $failed
            ? ($failGrades[0] ?? 'F')
            : $this->gradeForGpa($gpa);

        return [
            'exam_id' => $exam->id,
            'enrollment_id' => $enrollmentId,
            'total_marks' => $total,
            'gpa' => $gpa,
            'grade' => $grade,
            'is_passed' => ! $failed,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Map a GPA to its grade letter via the scale's grade points: the highest
     * band whose grade point the GPA reaches.
     */
    private function gradeForGpa(float $gpa): string
    {
        $band = $this->grades->all()
            ->sortByDesc('grade_point')
            ->first(fn ($band): bool => $gpa >= (float) $band->grade_point);

        return $band?->grade ?? 'F';
    }
}
