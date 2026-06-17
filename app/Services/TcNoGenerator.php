<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\TransferCertificate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates per-branch transfer certificate numbers of the form
 * `TC-{branchCode}-{seq}` (e.g. `TC-MP-0003`). The sequence is independent per
 * branch and is zero-padded to four digits.
 *
 * Race safety mirrors AdmissionNoGenerator: the branch row is locked FOR UPDATE
 * for the duration of the enclosing transaction so two simultaneous issues for
 * the same branch serialize. The DB unique index on tc_no is the final guard.
 * The caller (TransferCertificateService::issue) invokes generate() inside its
 * issue transaction so the lock spans the insert.
 */
class TcNoGenerator
{
    /**
     * Generate the next TC number for the given branch.
     */
    public function generate(int $branchId): string
    {
        return DB::transaction(function () use ($branchId) {
            $branch = Branch::lockForUpdate()->findOrFail($branchId);

            $prefix = sprintf('TC-%s-', $branch->code);

            $lastNo = TransferCertificate::withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->where('tc_no', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->value('tc_no');

            $seq = $lastNo === null ? 1 : ((int) Str::afterLast($lastNo, '-')) + 1;

            return sprintf('%s%04d', $prefix, $seq);
        });
    }
}
