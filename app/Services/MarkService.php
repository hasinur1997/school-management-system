<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarkService
{
    public function __construct(private readonly GradeResolver $grades) {}

    /**
     * Build the marks entry sheet for one subject of an exam, scoped to a
     * section: the section's active enrollments in roll order, each carrying the
     * mark already entered for this exam+subject (or null). TC/inactive
     * enrollments are excluded by the active-status filter.
     *
     * The roster is two queries — enrollments (with student) and the existing
     * marks keyed by enrollment — so there is no N+1.
     *
     * @return array{exam: Exam, subject: Subject, enrollments: \Illuminate\Database\Eloquent\Collection<int, Enrollment>, marks: Collection<int, Mark>}
     */
    public function sheet(Exam $exam, int $subjectId, int $sectionId): array
    {
        $subject = Subject::findOrFail($subjectId);

        $enrollments = Enrollment::query()
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::Active)
            ->with('student')
            ->orderBy('roll_no')
            ->get();

        $marks = Mark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subjectId)
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->get()
            ->keyBy('enrollment_id');

        return [
            'exam' => $exam,
            'subject' => $subject,
            'enrollments' => $enrollments,
            'marks' => $marks,
        ];
    }

    /**
     * Bulk-save marks for one subject of an exam. Structural validation
     * (published freeze, subject in class, active enrollments, range) is handled
     * by StoreMarksRequest; here we enforce the teacher-assignment rule and
     * perform a single bulk upsert per 500-row chunk with grade snapshots.
     *
     * Grade + grade point are resolved from the grading scale at entry and
     * stored, so later scale edits never alter saved marks. Re-posting updates
     * existing rows via the (exam_id, enrollment_id, subject_id) unique key, so
     * the operation is idempotent.
     *
     * @param  array<int, array{enrollment_id: int, obtained_marks: int|float}>  $marks
     * @return int the number of marks saved
     */
    public function saveBulk(Exam $exam, int $subjectId, array $marks, User $user): int
    {
        $this->assertAssignedToSubject($user, $subjectId);

        $now = now();

        $rows = array_map(function (array $row) use ($exam, $subjectId, $user, $now): array {
            $resolved = $this->grades->resolve($row['obtained_marks']);

            return [
                'public_id' => Mark::newPublicId(),
                'exam_id' => $exam->id,
                'enrollment_id' => $row['enrollment_id'],
                'subject_id' => $subjectId,
                'obtained_marks' => $row['obtained_marks'],
                'grade' => $resolved['grade'],
                'grade_point' => $resolved['grade_point'],
                'entered_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $marks);

        foreach (array_chunk($rows, 500) as $chunk) {
            Mark::upsert(
                $chunk,
                ['exam_id', 'enrollment_id', 'subject_id'],
                ['obtained_marks', 'grade', 'grade_point', 'entered_by', 'updated_at'],
            );
        }

        return count($rows);
    }

    /**
     * Browse marks of an exam in the caller's branch (scope is automatic via the
     * enrollment chain), filtered by subject/section. The enrollment + student
     * and subject are eager loaded so the resource never lazy loads.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(Exam $exam, array $filters, int $perPage): LengthAwarePaginator
    {
        return Mark::query()
            ->where('exam_id', $exam->id)
            ->with(['enrollment.student', 'subject'])
            ->when(isset($filters['subject_id']), fn (Builder $query) => $query->where('subject_id', $filters['subject_id']))
            ->when(
                isset($filters['section_id']),
                fn (Builder $query) => $query->whereHas('enrollment', fn (Builder $e) => $e->where('section_id', $filters['section_id'])),
            )
            ->orderBy('subject_id')
            ->orderBy('enrollment_id')
            ->paginate($perPage);
    }

    /**
     * Enforce the teacher-assignment rule: a user who has a teacher profile must
     * be assigned to the given subject via teacher_assignments. Non-teacher
     * staff who hold marks.entry (e.g. super admin) skip the check.
     */
    private function assertAssignedToSubject(User $user, int $subjectId): void
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher === null) {
            return;
        }

        $assigned = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to this subject');
    }
}
