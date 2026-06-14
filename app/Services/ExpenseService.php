<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ExpenseService
{
    /**
     * Browse expenses in the caller's branch (scope is automatic via branch_id).
     * Filters: category_id, inclusive from/to date range, item_name search. Sort
     * by date (default) or amount.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'date';
        $direction = $filters['direction'] ?? 'desc';

        return Expense::query()
            ->when(isset($filters['category_id']), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('date', '<=', $filters['to']))
            ->when(isset($filters['search']), fn (Builder $query) => $query->where('item_name', 'like', '%'.$filters['search'].'%'))
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    /**
     * Create a manual expense. branch_id is stamped by BelongsToBranch;
     * created_by is the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Expense
    {
        $data['created_by'] = Auth::id();

        return Expense::create($data);
    }

    /**
     * Update a manual expense.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense;
    }

    /**
     * Delete a manual expense.
     */
    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
