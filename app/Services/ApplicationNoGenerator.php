<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates per-branch admission application numbers of the form
 * `APP-{branchCode}-{seq}` (e.g. `APP-MP-00001`). The sequence is independent
 * per branch.
 *
 * Race safety: the branch row is locked FOR UPDATE for the duration of the
 * enclosing transaction, so two simultaneous submissions for the same branch
 * serialize — the second waits for the first to commit before reading the
 * latest sequence. The DB unique index on application_no is the final guard.
 */
class ApplicationNoGenerator
{
    /**
     * Generate the next application number for the given branch.
     */
    public function generate(int $branchId): string
    {
        return DB::transaction(function () use ($branchId) {
            $branch = Branch::lockForUpdate()->findOrFail($branchId);

            $lastNo = AdmissionApplication::withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->orderByDesc('id')
                ->value('application_no');

            $seq = $lastNo === null ? 1 : ((int) Str::afterLast($lastNo, '-')) + 1;

            return sprintf('APP-%s-%05d', $branch->code, $seq);
        });
    }
}
