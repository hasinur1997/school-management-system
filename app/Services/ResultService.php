<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AnnualResult;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Mark;
use App\Models\Student;
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

        // An exam covers a set of classes (or all of its branch); each
        // enrollment is graded against the subjects of its own class.
        $classIds = $exam->classIds();

        /** @var Collection<int|string, Collection<int, Subject>> $subjectsByClass */
        $subjectsByClass = Subject::query()
            ->whereIn('class_id', $classIds)
            ->get()
            ->groupBy('class_id');

        $enrollments = Enrollment::query()
            ->whereIn('class_id', $classIds)
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

            /** @var Collection<int, Subject> $subjects */
            $subjects = $subjectsByClass->get($enrollment->class_id) ?? collect();

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
     * Resolve the enrollment a result search points at. Either an admission_no
     * (→ the student's current enrollment, falling back to their latest) or the
     * full (session, class, section, roll) coordinates. Both styles resolve
     * through the branch-scoped Student model, so an out-of-branch match — or no
     * match at all — yields a 404.
     *
     * @param  array{admission_no?: string, session_id?: int, class_id?: int, section_id?: int, roll_no?: int}  $criteria
     */
    public function searchEnrollment(array $criteria): Enrollment
    {
        if (isset($criteria['admission_no'])) {
            $student = Student::query()
                ->where('admission_no', $criteria['admission_no'])
                ->firstOrFail();

            return $this->enrollmentForStudent($student, null);
        }

        return Enrollment::query()
            ->whereHas('student')
            ->where('session_id', $criteria['session_id'])
            ->where('class_id', $criteria['class_id'])
            ->where('section_id', $criteria['section_id'])
            ->where('roll_no', $criteria['roll_no'])
            ->firstOrFail();
    }

    /**
     * Resolve a single enrollment by id, scoped to the caller's branch through
     * its student (Enrollment has no branch_id of its own). Out-of-branch ids
     * 404. The student is eager loaded for the downstream policy check.
     */
    public function resolveEnrollment(int $id): Enrollment
    {
        return Enrollment::query()
            ->whereHas('student')
            ->with('student')
            ->findOrFail($id);
    }

    /**
     * Load a route-bound enrollment with the student needed for policy checks.
     */
    public function loadEnrollment(Enrollment $enrollment): Enrollment
    {
        return $enrollment->load('student');
    }

    /**
     * Resolve a student's enrollment for the result reads: the named session
     * when given, otherwise the current-session enrollment falling back to the
     * latest. A student with no matching enrollment → 404.
     */
    public function enrollmentForStudent(Student $student, ?int $sessionId): Enrollment
    {
        $enrollment = $sessionId !== null
            ? $student->enrollments()->where('session_id', $sessionId)->first()
            : ($student->currentEnrollment()->first()
                ?? $student->enrollments()->orderByDesc('session_id')->first());

        abort_if($enrollment === null, 404);

        return $enrollment;
    }

    /**
     * Build a student's full result bundle for one enrollment: the student
     * header, each per-exam result (in S1 → S2 → Final order) with its subject
     * marks, and the annual result.
     *
     * When $publishedOnly (students/parents) unpublished per-exam results are
     * omitted and the annual result is null unless published; otherwise every
     * result is included and flagged with its published state (staff preview).
     * Everything the resource touches is eager loaded — no N+1.
     *
     * @return array{
     *     enrollment: Enrollment,
     *     exam_results: Collection<int, ExamResult>,
     *     marks_by_exam: Collection<int|string, Collection<int, Mark>>,
     *     annual: ?AnnualResult,
     * }
     */
    public function bundle(Enrollment $enrollment, bool $publishedOnly): array
    {
        $enrollment->loadMissing(['student', 'schoolClass', 'section']);

        $examResults = ExamResult::query()
            ->where('enrollment_id', $enrollment->id)
            ->when($publishedOnly, fn (Builder $query) => $query->whereNotNull('published_at'))
            ->with('exam')
            ->get()
            ->sortBy(fn (ExamResult $result): int => array_search($result->exam->type, ExamType::cases(), true))
            ->values();

        $marksByExam = Mark::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('exam_id', $examResults->pluck('exam_id'))
            ->with('subject')
            ->get()
            ->groupBy('exam_id');

        $annual = AnnualResult::query()
            ->where('enrollment_id', $enrollment->id)
            ->when($publishedOnly, fn (Builder $query) => $query->whereNotNull('published_at'))
            ->first();

        return [
            'enrollment' => $enrollment,
            'exam_results' => $examResults,
            'marks_by_exam' => $marksByExam,
            'annual' => $annual,
        ];
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
            'public_id' => ExamResult::newPublicId(),
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
