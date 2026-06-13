<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates per-branch student admission numbers of the form
 * `STU-{branchCode}-{year}-{seq}` (e.g. `STU-MP-2026-00001`). The sequence is
 * independent per branch and per admission year.
 *
 * Race safety: the branch row is locked FOR UPDATE for the duration of the
 * enclosing transaction, so two simultaneous admissions for the same branch
 * serialize — the second waits for the first to commit before reading the
 * latest sequence. The DB unique index on admission_no is the final guard.
 * The caller (Task 3.5 approval) should invoke generate() inside its create
 * transaction so the lock spans the insert.
 */
class AdmissionNoGenerator
{
    /**
     * Generate the next admission number for the given branch and year.
     */
    public function generate(int $branchId, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($branchId, $year) {
            $branch = Branch::lockForUpdate()->findOrFail($branchId);

            $prefix = sprintf('STU-%s-%d-', $branch->code, $year);

            $lastNo = Student::withoutGlobalScope(BranchScope::class)
                ->withTrashed()
                ->where('branch_id', $branchId)
                ->where('admission_no', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->value('admission_no');

            $seq = $lastNo === null ? 1 : ((int) Str::afterLast($lastNo, '-')) + 1;

            return sprintf('%s%05d', $prefix, $seq);
        });
    }
}
