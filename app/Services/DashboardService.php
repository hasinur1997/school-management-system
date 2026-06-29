<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AssetStatus;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Asset;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The role-aware dashboard (Task 14.2). One entry point — {@see for()} — picks
 * the assembler for the caller's role and returns a shape that matches it:
 * staff (super admin / admin / accountant), teacher, student, or parent. Every
 * figure is a SQL aggregate (COUNT / SUM / GROUP BY), never a PHP loop, and the
 * assembled payload is cached for five minutes per user + role-view + branch.
 *
 * Branch isolation is automatic: the branch-scoped models carry BranchScope, so
 * a non-super-admin only ever sees their own branch and a super admin sees the
 * consolidated figures. Student attendance, which has no branch column, is
 * pinned through its enrollment's student instead.
 */
class DashboardService
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * The dashboard payload for the authenticated user, cached per
     * user + role-view + branch for five minutes.
     *
     * @return array<string, mixed>
     */
    public function for(User $user, ?int $branchFilter = null): array
    {
        $view = $this->roleView($user);

        // Only super admins choose a branch; everyone else is pinned to their
        // own branch by BranchScope, so the explicit filter stays null for them.
        $branch = $user->isSuperAdmin() ? $branchFilter : null;
        $branchId = $user->branch_id ?? 0;
        $branchKey = $branch ?? 'all';

        return Cache::remember(
            "dashboard:{$branchId}:{$branchKey}:{$view}:{$user->id}",
            self::CACHE_TTL,
            fn (): array => match ($view) {
                'staff' => $this->staff($branch),
                'teacher' => $this->teacher($user),
                'parent' => $this->parent($user),
                default => $this->student($user),
            },
        );
    }

    /**
     * The view a user's role maps to. Staff (super admin / admin / accountant)
     * share one operational summary; teacher, student and parent each get their
     * own. Precedence resolves the (unexpected) multi-role user deterministically.
     */
    private function roleView(User $user): string
    {
        if ($user->isSuperAdmin() || $user->hasRole(['admin', 'accountant'])) {
            return 'staff';
        }

        if ($user->hasRole('teacher')) {
            return 'teacher';
        }

        if ($user->hasRole('parent')) {
            return 'parent';
        }

        return 'student';
    }

    /**
     * Staff view: today's student attendance %, pending admissions, this
     * month's income/expense/net, the count of invoices still owing, and the
     * branch totals (active students, active teachers, asset value).
     *
     * A super admin may pass a branch id to narrow every figure to one branch;
     * null aggregates across all branches. For branch-bound staff BranchScope
     * already constrains these queries, so the caller passes null.
     *
     * @return array<string, mixed>
     */
    private function staff(?int $branch = null): array
    {
        [$monthStart, $monthEnd] = [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];

        $scoped = fn (Builder $query): Builder => $query
            ->when($branch !== null, fn (Builder $q) => $q->where('branch_id', $branch));

        $income = (string) $scoped(Income::query())
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
        $expense = (string) $scoped(Expense::query())
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        return [
            'role_view' => 'staff',
            'today_attendance_percent' => $this->todayAttendancePercent($branch),
            'pending_admissions' => (int) $scoped(AdmissionApplication::query())
                ->where('status', AdmissionStatus::Pending->value)
                ->count(),
            'month' => [
                'income' => $this->money($income),
                'expense' => $this->money($expense),
                'net' => $this->money(bcsub($income, $expense, 2)),
            ],
            'unpaid_invoices' => (int) $scoped(Invoice::query())
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->count(),
            'totals' => [
                'students' => (int) $scoped(Student::query())->where('status', StudentStatus::Active->value)->count(),
                'teachers' => (int) $scoped(Teacher::query())->where('status', TeacherStatus::Active->value)->count(),
                'asset_value' => $this->money($this->assetValue($branch)),
            ],
        ];
    }

    /**
     * Teacher view: whether the teacher has checked in today, the class/section
     * rosters they are assigned to this session, and the subset of those rosters
     * with no student attendance recorded yet today.
     *
     * @return array<string, mixed>
     */
    private function teacher(User $user): array
    {
        $teacher = Teacher::query()->where('user_id', $user->id)->firstOrFail();
        $sessionId = $this->currentSessionId();

        $assignments = TeacherAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->when($sessionId !== null, fn ($query) => $query->where('session_id', $sessionId))
            ->with(['schoolClass', 'section'])
            ->get();

        // Distinct class/section rosters the teacher owns this session.
        $rosters = $assignments
            ->map(fn (TeacherAssignment $a): array => [
                'section_id' => $a->section_id,
                'class' => $a->schoolClass?->name,
                'section' => $a->section?->name,
            ])
            ->unique(fn (array $r): string => $r['class'].'|'.($r['section'] ?? ''))
            ->values();

        // Sections that already have a recorded mark today, so we can subtract.
        $sectionIds = $rosters->pluck('section_id')->filter()->unique()->all();
        $takenToday = $sectionIds === []
            ? collect()
            : StudentAttendance::query()
                ->whereDate('student_attendances.date', Carbon::today()->toDateString())
                ->whereHas('enrollment.student')
                ->join('enrollments', 'student_attendances.enrollment_id', '=', 'enrollments.id')
                ->whereIn('enrollments.section_id', $sectionIds)
                ->distinct()
                ->pluck('enrollments.section_id');

        $pending = $rosters
            ->filter(fn (array $r): bool => $r['section_id'] !== null && ! $takenToday->contains($r['section_id']))
            ->map(fn (array $r): array => ['class' => $r['class'], 'section' => $r['section']])
            ->values()
            ->all();

        return [
            'role_view' => 'teacher',
            'checked_in' => TeacherAttendance::query()
                ->where('teacher_id', $teacher->id)
                ->whereDate('date', Carbon::today()->toDateString())
                ->whereNotNull('check_in_at')
                ->exists(),
            'classes' => $rosters
                ->map(fn (array $r): array => ['class' => $r['class'], 'section' => $r['section']])
                ->all(),
            'attendance_pending' => $pending,
        ];
    }

    /**
     * Student view: this month's attendance summary, the invoices still owing,
     * and the latest published exam result.
     *
     * @return array<string, mixed>
     */
    private function student(User $user): array
    {
        $student = Student::query()->where('user_id', $user->id)->firstOrFail();

        return ['role_view' => 'student'] + $this->studentBlock($student);
    }

    /**
     * Parent view: one student block per linked child (linked-only — the
     * students relation carries BranchScope, so no cross-branch child leaks).
     *
     * @return array<string, mixed>
     */
    private function parent(User $user): array
    {
        $parent = ParentProfile::query()->where('user_id', $user->id)->firstOrFail();

        $children = $parent->students()
            ->get()
            ->map(fn (Student $student): array => ['student_id' => $student->id, 'name' => $student->name_en]
                + $this->studentBlock($student))
            ->all();

        return [
            'role_view' => 'parent',
            'children' => $children,
        ];
    }

    /**
     * The shared student summary used by both the student and parent views:
     * this month's attendance summary, unpaid/partial invoices, and the latest
     * published exam result (null when none).
     *
     * @return array<string, mixed>
     */
    private function studentBlock(Student $student): array
    {
        $summary = $this->attendance->monthlySheet(
            $student,
            (int) Carbon::now()->month,
            (int) Carbon::now()->year,
        )['summary'];

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'invoice_no' => $invoice->invoice_no,
                'month' => $invoice->month,
                'year' => $invoice->year,
                'amount' => $this->money((string) $invoice->amount),
                'paid_amount' => $this->money((string) $invoice->paid_amount),
                'status' => $invoice->status->value,
                'due_date' => $invoice->due_date?->toDateString(),
            ])
            ->all();

        return [
            'attendance' => $summary,
            'unpaid_invoices' => $invoices,
            'latest_result' => $this->latestResult($student),
        ];
    }

    /**
     * The student's most recently published exam result, or null when none has
     * been published.
     *
     * @return array<string, mixed>|null
     */
    private function latestResult(Student $student): ?array
    {
        $result = ExamResult::query()
            ->whereIn('enrollment_id', $student->enrollments()->pluck('id'))
            ->whereNotNull('published_at')
            ->with('exam')
            ->orderByDesc('published_at')
            ->first();

        if ($result === null) {
            return null;
        }

        return [
            'exam' => $result->exam?->name,
            'gpa' => (string) $result->gpa,
            'grade' => $result->grade,
            'is_passed' => (bool) $result->is_passed,
            'published_at' => $result->published_at?->toIso8601String(),
        ];
    }

    /**
     * Today's student attendance percentage: present + late over every mark
     * recorded today, one decimal place, scoped to in-branch students through
     * the enrollment chain. Zero when nothing has been recorded yet.
     */
    private function todayAttendancePercent(?int $branch = null): float
    {
        $row = StudentAttendance::query()
            ->when($branch !== null, fn (Builder $query) => $query->where('branch_id', $branch))
            ->whereDate('date', Carbon::today()->toDateString())
            ->whereHas('enrollment.student')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as attended', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])
            ->first();

        $total = (int) ($row->total ?? 0);

        if ($total === 0) {
            return 0.0;
        }

        return round((int) $row->attended / $total * 100, 1);
    }

    /**
     * The branch's asset value: the sum of in_use + damaged asset values,
     * disposed assets excluded (the Task 11.4 rule).
     */
    private function assetValue(?int $branch = null): string
    {
        return (string) Asset::query()
            ->when($branch !== null, fn (Builder $query) => $query->where('branch_id', $branch))
            ->whereIn('status', [AssetStatus::InUse->value, AssetStatus::Damaged->value])
            ->sum('value');
    }

    /**
     * The id of the current academic session, or null when none is marked.
     */
    private function currentSessionId(): ?int
    {
        return AcademicSession::query()->where('is_current', true)->value('id');
    }

    /**
     * Normalise a numeric/string amount to a fixed two-decimal string.
     */
    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
