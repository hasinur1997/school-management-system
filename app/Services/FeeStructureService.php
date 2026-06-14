<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FeeStructureService
{
    /**
     * Browse fee structures in the caller's branch (scope is automatic via
     * branch_id), filtered by session/class.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return FeeStructure::query()
            ->when(isset($filters['session_id']), fn (Builder $query) => $query->where('session_id', $filters['session_id']))
            ->when(isset($filters['class_id']), fn (Builder $query) => $query->where('class_id', $filters['class_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a fee structure. The branch is taken from the (already
     * branch-validated) class — a class belongs to exactly one branch — so
     * super admins (who carry no branch of their own) get a correct branch_id
     * and the BelongsToBranch stamp stays consistent for everyone else.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FeeStructure
    {
        $class = SchoolClass::findOrFail($data['class_id']);

        return FeeStructure::create([...$data, 'branch_id' => $class->branch_id]);
    }

    /**
     * Update a fee structure's amount. Only monthly_fee is editable; this
     * affects only future invoice generation — existing invoices keep their
     * copied amount.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(FeeStructure $feeStructure, array $data): FeeStructure
    {
        $feeStructure->update(['monthly_fee' => $data['monthly_fee']]);

        return $feeStructure;
    }
}
