<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Support\SettingsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Owns monthly invoice generation (10.2): for every active student with an
 * active enrollment, an invoice is created for the given month from the class
 * fee structure, copying the amount. Generation is idempotent — the unique
 * (student, month, year) index means a re-run skips existing invoices rather
 * than duplicating them. Branch isolation rides on the branch-scoped Student
 * relation, so the manual endpoint generates only the caller's branch while
 * the scheduler (no auth user) generates every branch.
 */
class InvoiceService
{
    /** Bulk inserts are chunked at this size per the performance rules. */
    private const CHUNK = 500;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Generate invoices for the given month/year. Active (non-TC, non-inactive)
     * students with an active enrollment are invoiced from their class's fee
     * structure; students already invoiced for the period are skipped, and
     * classes lacking a fee structure are reported rather than invoiced.
     *
     * @return array{created: int, skipped_existing: int, missing_fee_structure: list<array{class_id: int}>}
     */
    public function generate(int $month, int $year): array
    {
        // Candidate enrollments: an active enrollment of an active student.
        // Student is branch-scoped (auto in the manual endpoint, all branches
        // when run from the scheduler with no auth user). TC/inactive students
        // and closed enrollments are excluded by these status filters.
        $enrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas('student', fn (Builder $query) => $query->where('status', StudentStatus::Active->value))
            ->with('student:id,branch_id')
            ->get();

        $created = 0;
        $skippedExisting = 0;
        $missingClassIds = [];

        if ($enrollments->isEmpty()) {
            return $this->report($created, $skippedExisting, $missingClassIds);
        }

        $branchIds = $enrollments->pluck('student.branch_id')->unique()->all();

        $branches = Branch::query()->whereKey($branchIds)->get()->keyBy('id');

        // Fee structures keyed by branch-session-class for an O(1) lookup of the
        // amount to copy.
        $feeMap = FeeStructure::query()
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy(fn (FeeStructure $fee): string => "{$fee->branch_id}-{$fee->session_id}-{$fee->class_id}");

        // Students already invoiced for this period — the idempotency skip set.
        $alreadyInvoiced = Invoice::query()
            ->where('month', $month)
            ->where('year', $year)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->pluck('student_id')
            ->flip();

        $period = sprintf('%04d%02d', $year, $month);
        $now = now();

        // Process per branch so the invoice_no sequence and branch code stay
        // correct within each branch.
        foreach ($enrollments->groupBy('student.branch_id') as $branchId => $group) {
            $branch = $branches->get($branchId);

            // Each branch's invoices fall due on its own configured due day.
            $dueDate = Carbon::create($year, $month, $this->settings->invoiceDueDay((int) $branchId))->toDateString();

            // Continue the per-(branch, month, year) sequence from any invoices
            // already generated for the period.
            $seq = Invoice::query()
                ->where('branch_id', $branchId)
                ->where('month', $month)
                ->where('year', $year)
                ->count();

            $rows = [];

            foreach ($group as $enrollment) {
                if ($alreadyInvoiced->has($enrollment->student_id)) {
                    $skippedExisting++;

                    continue;
                }

                $fee = $feeMap->get("{$branchId}-{$enrollment->session_id}-{$enrollment->class_id}");

                if ($fee === null) {
                    $missingClassIds[$enrollment->class_id] = true;

                    continue;
                }

                $seq++;

                $rows[] = [
                    'branch_id' => $branchId,
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                    'invoice_no' => sprintf('INV-%s-%s-%04d', $branch->code, $period, $seq),
                    'month' => $month,
                    'year' => $year,
                    'amount' => $fee->monthly_fee,
                    'paid_amount' => '0.00',
                    'status' => InvoiceStatus::Unpaid->value,
                    'due_date' => $dueDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                Invoice::query()->insert($chunk);
                $created += count($chunk);
            }
        }

        return $this->report($created, $skippedExisting, $missingClassIds);
    }

    /**
     * Browse invoices in the caller's branch (scope is automatic via branch_id),
     * filtered by student/class/status/month/year, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('student:id,name_en')
            ->when(isset($filters['student_id']), fn (Builder $query) => $query->where('student_id', $filters['student_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['month']), fn (Builder $query) => $query->where('month', $filters['month']))
            ->when(isset($filters['year']), fn (Builder $query) => $query->where('year', $filters['year']))
            ->when(isset($filters['class_id']), fn (Builder $query) => $query->whereHas(
                'enrollment',
                fn (Builder $relation) => $relation->where('class_id', $filters['class_id']),
            ))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Resolve one invoice (branch-scoped → out-of-branch 404) with its student
     * eager-loaded for the detail view.
     */
    public function find(int $id): Invoice
    {
        // user_id is needed by StudentPolicy::viewInvoices to match a student to
        // their own invoice.
        return Invoice::query()
            ->with([
                'student:id,name_en,user_id',
                'payments' => fn ($query) => $query->latest('id'),
            ])
            ->findOrFail($id);
    }

    /**
     * The given student's invoices for a year (or all years), newest first.
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function forStudent(Student $student, ?int $year, int $perPage): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('student:id,name_en')
            ->where('student_id', $student->id)
            ->when($year !== null, fn (Builder $query) => $query->where('year', $year))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Shape the generation summary, collapsing the missing-class set into the
     * contract's list form.
     *
     * @param  array<int, true>  $missingClassIds
     * @return array{created: int, skipped_existing: int, missing_fee_structure: list<array{class_id: int}>}
     */
    private function report(int $created, int $skippedExisting, array $missingClassIds): array
    {
        return [
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'missing_fee_structure' => array_map(
                fn (int $classId): array => ['class_id' => $classId],
                array_keys($missingClassIds),
            ),
        ];
    }
}
