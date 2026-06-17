<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherAttendanceStatus;
use App\Enums\TeacherStatus;
use App\Models\AcademicSession;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Support\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The student / teacher / asset / fees reports (Task 13.3). Like the finance
 * reports (Task 13.2) every figure is computed in SQL (COUNT/SUM + GROUP BY) —
 * there is no PHP-side aggregation — over the shared {@see ReportFilter} window
 * and branch scope. A null branch on the filter is the consolidated (super
 * admin) view: branch isolation is already bypassed there, so the queries span
 * every branch; otherwise each query is pinned to the single branch.
 */
class EntityReportService
{
    /**
     * Student report: total head-count, a per-status breakdown, the per-class
     * distribution of active students (via their current-session enrollment),
     * and the number of admissions inside the resolved window.
     *
     * @return array<string, mixed>
     */
    public function students(ReportFilter $filter): array
    {
        [$start, $end] = $filter->range();

        $byStatus = $this->branchScoped(Student::query(), 'students', $filter)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = [];
        $total = 0;
        foreach (StudentStatus::cases() as $status) {
            $count = (int) ($byStatus[$status->value] ?? 0);
            $statuses[$status->value] = $count;
            $total += $count;
        }

        $newAdmissions = (int) $this->branchScoped(Student::query(), 'students', $filter)
            ->whereBetween('students.admitted_at', [$start, $end])
            ->count();

        return [
            'filters' => $this->filtersBlock($filter),
            'total' => $total,
            'by_status' => $statuses,
            'by_class' => $this->studentsByClass($filter),
            'new_admissions' => $newAdmissions,
        ];
    }

    /**
     * Teacher report: total head-count, a per-status breakdown, and an
     * attendance summary (present/late/absent/leave counts) over the window.
     *
     * @return array<string, mixed>
     */
    public function teachers(ReportFilter $filter): array
    {
        [$start, $end] = $filter->range();

        $byStatus = $this->branchScoped(Teacher::query(), 'teachers', $filter)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = [];
        $total = 0;
        foreach (TeacherStatus::cases() as $status) {
            $count = (int) ($byStatus[$status->value] ?? 0);
            $statuses[$status->value] = $count;
            $total += $count;
        }

        $attendanceRows = TeacherAttendance::query()
            ->join('teachers', 'teacher_attendances.teacher_id', '=', 'teachers.id')
            ->when(
                $filter->branchId !== null,
                fn (Builder $query) => $query->where('teachers.branch_id', $filter->branchId),
            )
            ->whereBetween('teacher_attendances.date', [$start, $end])
            ->selectRaw('teacher_attendances.status, COUNT(*) as count')
            ->groupBy('teacher_attendances.status')
            ->pluck('count', 'status');

        $attendance = [];
        foreach (TeacherAttendanceStatus::cases() as $status) {
            $attendance[$status->value] = (int) ($attendanceRows[$status->value] ?? 0);
        }

        return [
            'filters' => $this->filtersBlock($filter),
            'total' => $total,
            'by_status' => $statuses,
            'attendance' => $attendance,
        ];
    }

    /**
     * Asset report: total value (in_use + damaged, disposed excluded — the
     * Task 11.4 rule), a per-status breakdown (count + value), and the assets
     * added inside the window by purchase_date (count + value).
     *
     * @return array<string, mixed>
     */
    public function assets(ReportFilter $filter): array
    {
        [$start, $end] = $filter->range();

        $rows = $this->branchScoped(Asset::query(), 'assets', $filter)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(value), 0) as value')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status->value);

        $byStatus = [];
        $totalValue = '0';
        $count = 0;
        foreach (AssetStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $statusCount = (int) ($row->count ?? 0);
            $statusValue = (string) ($row->value ?? '0');

            $byStatus[$status->value] = [
                'count' => $statusCount,
                'value' => $this->money($statusValue),
            ];

            $count += $statusCount;

            if ($status !== AssetStatus::Disposed) {
                $totalValue = bcadd($totalValue, $statusValue, 2);
            }
        }

        $additions = $this->branchScoped(Asset::query(), 'assets', $filter)
            ->whereBetween('assets.purchase_date', [$start, $end])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(value), 0) as value')
            ->first();

        return [
            'filters' => $this->filtersBlock($filter),
            'total_value' => $this->money($totalValue),
            'count' => $count,
            'by_status' => $byStatus,
            'additions' => [
                'count' => (int) ($additions->count ?? 0),
                'value' => $this->money((string) ($additions->value ?? '0')),
            ],
        ];
    }

    /**
     * Fees report: invoiced / collected / outstanding totals (outstanding =
     * invoiced − collected), the same trio per billing month inside the window,
     * and an invoiced/collected breakdown per class. Collected is each
     * invoice's paid_amount, which equals the payments recorded against it, so
     * the figures reconcile with both the invoice and payment fixtures.
     *
     * @return array<string, mixed>
     */
    public function fees(ReportFilter $filter): array
    {
        $totals = $this->feeInvoices($filter)
            ->selectRaw('COALESCE(SUM(amount), 0) as invoiced, COALESCE(SUM(paid_amount), 0) as collected')
            ->first();

        $invoiced = (string) ($totals->invoiced ?? '0');
        $collected = (string) ($totals->collected ?? '0');

        return [
            'filters' => $this->filtersBlock($filter),
            'totals' => [
                'invoiced' => $this->money($invoiced),
                'collected' => $this->money($collected),
                'outstanding' => $this->money(bcsub($invoiced, $collected, 2)),
            ],
            'by_month' => $this->feesByMonth($filter),
            'by_class' => $this->feesByClass($filter),
        ];
    }

    /**
     * Per-class distribution of active students, resolved through their
     * enrollment in the current academic session. Ordered by class level; empty
     * when no session is marked current.
     *
     * @return array<int, array<string, int|string>>
     */
    private function studentsByClass(ReportFilter $filter): array
    {
        $sessionId = AcademicSession::query()->where('is_current', true)->value('id');

        if ($sessionId === null) {
            return [];
        }

        return $this->branchScoped(Student::query(), 'students', $filter)
            ->where('students.status', StudentStatus::Active->value)
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->where('enrollments.session_id', $sessionId)
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.class_id')
            ->selectRaw('school_classes.name as class_name, COUNT(*) as count')
            ->groupBy('school_classes.id', 'school_classes.name', 'school_classes.numeric_level')
            ->orderBy('school_classes.numeric_level')
            ->get()
            ->map(fn ($row) => [
                'class' => (string) $row->class_name,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * Per-billing-month invoiced / collected / outstanding, ascending by month.
     *
     * @return array<int, array<string, string>>
     */
    private function feesByMonth(ReportFilter $filter): array
    {
        $bucket = $this->monthBucket();

        return $this->feeInvoices($filter)
            ->selectRaw("{$bucket} as bucket, COALESCE(SUM(amount), 0) as invoiced, COALESCE(SUM(paid_amount), 0) as collected")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(function ($row) {
                $invoiced = (string) $row->invoiced;
                $collected = (string) $row->collected;

                return [
                    'month' => substr((string) $row->bucket, 0, 7),
                    'invoiced' => $this->money($invoiced),
                    'collected' => $this->money($collected),
                    'outstanding' => $this->money(bcsub($invoiced, $collected, 2)),
                ];
            })
            ->all();
    }

    /**
     * Per-class invoiced / collected, joined through the invoice's enrollment.
     *
     * @return array<int, array<string, string>>
     */
    private function feesByClass(ReportFilter $filter): array
    {
        return $this->feeInvoices($filter)
            ->join('enrollments', 'invoices.enrollment_id', '=', 'enrollments.id')
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.class_id')
            ->selectRaw('school_classes.name as class_name, COALESCE(SUM(invoices.amount), 0) as invoiced, COALESCE(SUM(invoices.paid_amount), 0) as collected')
            ->groupBy('school_classes.id', 'school_classes.name', 'school_classes.numeric_level')
            ->orderBy('school_classes.numeric_level')
            ->get()
            ->map(fn ($row) => [
                'class' => (string) $row->class_name,
                'invoiced' => $this->money((string) $row->invoiced),
                'collected' => $this->money((string) $row->collected),
            ])
            ->all();
    }

    /**
     * Invoices whose billing month (year-month) falls inside the resolved
     * window, scoped to the filter's branch. Membership is decided on the first
     * of the billing month so any invoice for a month touched by the range is
     * counted.
     */
    private function feeInvoices(ReportFilter $filter): Builder
    {
        [$start, $end] = $filter->range();
        $bucket = $this->monthBucket();

        return $this->branchScoped(Invoice::query(), 'invoices', $filter)
            ->whereRaw("{$bucket} >= ?", [$start->startOfMonth()->toDateString()])
            ->whereRaw("{$bucket} <= ?", [$end->toDateString()]);
    }

    /**
     * Restrict a branch-scoped model's query to the filter's branch when one is
     * set. The global BranchScope already pins non-super-admins to their own
     * branch; this mirrors the finance reports' explicit filter so a super admin
     * can target a single branch, and is a no-op for the consolidated view.
     */
    private function branchScoped(Builder $query, string $table, ReportFilter $filter): Builder
    {
        if ($filter->branchId !== null) {
            $query->where("{$table}.branch_id", $filter->branchId);
        }

        return $query;
    }

    /**
     * The resolved filters echoed back to the caller.
     *
     * @return array<string, string>
     */
    private function filtersBlock(ReportFilter $filter): array
    {
        [$start, $end] = $filter->range();

        return [
            'period' => $filter->period->value,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'branch' => $filter->branchId === null
                ? 'All Branches'
                : (Branch::find($filter->branchId)?->name ?? 'All Branches'),
        ];
    }

    /**
     * A driver-appropriate expression producing the first day of an invoice's
     * billing month (year + month columns) as a 'YYYY-MM-01' string, usable for
     * both range comparison and GROUP BY.
     */
    private function monthBucket(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "printf('%04d-%02d-01', invoices.year, invoices.month)"
            : "CONCAT(LPAD(invoices.year, 4, '0'), '-', LPAD(invoices.month, 2, '0'), '-01')";
    }

    /**
     * Normalise a numeric/string amount to a fixed two-decimal string.
     */
    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
