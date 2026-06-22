<?php

namespace App\Services;

use App\Models\Income;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class IncomeService
{
    /**
     * Browse incomes in the caller's branch (scope is automatic via branch_id).
     * Filters: category_id, inclusive from/to date range, title search. Sort by
     * date (default) or amount.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'date';
        $direction = $filters['direction'] ?? 'desc';

        return Income::query()
            ->with('category')
            ->when(isset($filters['category_id']), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('date', '<=', $filters['to']))
            ->when(isset($filters['search']), fn (Builder $query) => $query->where('title', 'like', '%'.$filters['search'].'%'))
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    /**
     * Create a manual income. branch_id is stamped by BelongsToBranch;
     * created_by is the authenticated user. payment_id stays null, so the row
     * is editable (not system-generated).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Income
    {
        $data['created_by'] = Auth::id();

        return Income::create($data)->load('category');
    }

    /**
     * Update a manual income. System-generated fee income (payment_id set) is
     * immutable → 403.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Income $income, array $data): Income
    {
        $this->guardSystemRow($income);

        $income->update($data);

        return $income->load('category');
    }

    /**
     * Delete a manual income. System-generated fee income is immutable → 403.
     */
    public function delete(Income $income): void
    {
        $this->guardSystemRow($income);

        $income->delete();
    }

    /**
     * Reject mutations on system-generated fee income.
     */
    private function guardSystemRow(Income $income): void
    {
        abort_if($income->isSystem(), 403, 'System-generated income cannot be modified');
    }
}
