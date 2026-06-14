<?php

namespace App\Services;

use App\Enums\TeacherAttendanceStatus;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherAttendanceService
{
    /**
     * Browse teacher attendance in the caller's branch (scope is automatic via
     * the teacher relation), filtered by teacher/date/month/year/status. The
     * teacher is eager loaded so the resource never lazy loads its name.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return TeacherAttendance::query()
            ->with('teacher')
            ->when(isset($filters['teacher_id']), fn (Builder $query) => $query->where('teacher_id', $filters['teacher_id']))
            ->when(isset($filters['date']), fn (Builder $query) => $query->whereDate('date', $filters['date']))
            ->when(isset($filters['month']), fn (Builder $query) => $query->whereMonth('date', $filters['month']))
            ->when(isset($filters['year']), fn (Builder $query) => $query->whereYear('date', $filters['year']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('date')
            ->orderBy('teacher_id')
            ->paginate($perPage);
    }

    /**
     * Apply an admin correction to a record (status and/or check-in/out times),
     * stamping the acting admin as corrected_by. Time-order validity is enforced
     * by UpdateTeacherAttendanceRequest. The record is already branch-scoped by
     * route-model binding (out-of-branch → 404).
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(TeacherAttendance $record, array $data, User $admin): TeacherAttendance
    {
        $record->fill(array_intersect_key($data, array_flip(['status', 'check_in_at', 'check_out_at'])));
        $record->corrected_by = $admin->id;
        $record->save();

        return $record->load(['teacher', 'correctedBy']);
    }

    /**
     * Build a teacher's monthly history: a SQL-aggregated status summary and the
     * ordered day-by-day records, both scoped to the teacher and month/year. The
     * summary is one aggregate query — never a PHP loop over rows.
     *
     * @return array{summary: array{present: int, late: int, absent: int, leave: int}, records: Collection<int, TeacherAttendance>}
     */
    public function monthly(Teacher $teacher, int $month, int $year): array
    {
        $base = fn (): Builder => TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $aggregate = $base()
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as present,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as late,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as absent,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as leave_count',
                [
                    TeacherAttendanceStatus::Present->value,
                    TeacherAttendanceStatus::Late->value,
                    TeacherAttendanceStatus::Absent->value,
                    TeacherAttendanceStatus::Leave->value,
                ],
            )
            ->first();

        $records = $base()->orderBy('date')->get();

        return [
            'summary' => [
                'present' => (int) $aggregate->present,
                'late' => (int) $aggregate->late,
                'absent' => (int) $aggregate->absent,
                'leave' => (int) $aggregate->leave_count,
            ],
            'records' => $records,
        ];
    }
}
