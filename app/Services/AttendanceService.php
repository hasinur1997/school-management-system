<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Build the attendance entry sheet for a section on a given date: the active
     * enrollments of the section in roll order, each carrying that date's
     * existing mark (or null when not yet taken). TC/inactive enrollments are
     * excluded by the active-status filter, so they never appear on the roster.
     *
     * The roster is two queries — enrollments (with student + photo media) and
     * the day's attendance rows keyed by enrollment — so there is no N+1.
     *
     * @return array{date: string, section: Section, enrollments: \Illuminate\Database\Eloquent\Collection<int, Enrollment>, records: Collection<int, StudentAttendance>}
     */
    public function sheet(int $sectionId, Carbon $date): array
    {
        $section = Section::with('schoolClass')->findOrFail($sectionId);

        $enrollments = Enrollment::query()
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::Active)
            ->with('student.media')
            ->orderBy('roll_no')
            ->get();

        $records = StudentAttendance::query()
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('enrollment_id');

        return [
            'date' => $date->toDateString(),
            'section' => $section,
            'enrollments' => $enrollments,
            'records' => $records,
        ];
    }

    /**
     * Bulk-save a section's attendance for one date. Structural validation
     * (branch-scoped section, non-future date, active in-section enrollments)
     * is handled by StoreAttendanceRequest; here we enforce the teacher
     * assignment rule and perform a single bulk upsert per 500-row chunk.
     *
     * A teacher (a user with a teacher profile) must be assigned to the
     * section's class via teacher_assignments; non-teacher staff who hold
     * attendance.create (admins) bypass the check. Re-posting the same date
     * updates existing rows via the (enrollment_id, date) unique key, so the
     * operation is idempotent.
     *
     * @param  array<int, array{enrollment_id: int, status: string}>  $records
     * @return int the number of records saved
     */
    public function saveBulk(int $sectionId, string $date, array $records, User $user): int
    {
        $section = Section::findOrFail($sectionId);

        $this->assertAssignedToClass($user, $section->class_id);

        $now = now();

        $rows = array_map(fn (array $record): array => [
            'enrollment_id' => $record['enrollment_id'],
            'date' => $date,
            'status' => $record['status'],
            'recorded_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);

        foreach (array_chunk($rows, 500) as $chunk) {
            StudentAttendance::upsert(
                $chunk,
                ['enrollment_id', 'date'],
                ['status', 'recorded_by', 'updated_at'],
            );
        }

        return count($rows);
    }

    /**
     * Correct a single attendance record's status. The record is already
     * branch-scoped by route-model binding (out-of-branch → 404).
     */
    public function update(StudentAttendance $attendance, string $status): StudentAttendance
    {
        $attendance->update(['status' => $status]);

        return $attendance->load('enrollment.student');
    }

    /**
     * Browse attendance records in the caller's branch (scope is automatic via
     * the enrollment chain), filtered by class/section/date/status. The
     * enrollment + student are eager loaded so the resource never lazy loads.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return StudentAttendance::query()
            ->with(['enrollment.student'])
            ->when(
                isset($filters['class_id']) || isset($filters['section_id']),
                fn (Builder $query) => $query->whereHas('enrollment', function (Builder $enrollment) use ($filters): void {
                    $enrollment
                        ->when(isset($filters['class_id']), fn (Builder $q) => $q->where('class_id', $filters['class_id']))
                        ->when(isset($filters['section_id']), fn (Builder $q) => $q->where('section_id', $filters['section_id']));
                })
            )
            ->when(isset($filters['date']), fn (Builder $query) => $query->whereDate('date', $filters['date']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('date')
            ->orderBy('enrollment_id')
            ->paginate($perPage);
    }

    /**
     * Enforce the teacher assignment rule: a user who has a teacher profile
     * must be assigned to the given class via teacher_assignments. Non-teacher
     * staff (admins holding attendance.create) skip the check.
     */
    private function assertAssignedToClass(User $user, int $classId): void
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher === null) {
            return;
        }

        $assigned = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to this class');
    }
}
