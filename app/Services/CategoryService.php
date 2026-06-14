<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CategoryService
{
    /**
     * Browse categories in the caller's branch (scope is automatic via
     * branch_id), optionally filtered by type.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Category::query()
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a category. branch_id is stamped automatically by BelongsToBranch.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update a category's name/type.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    /**
     * Delete a category. A category referenced by any income (or expense) row
     * is in use and cannot be removed — 409.
     */
    public function delete(Category $category): void
    {
        abort_if($category->incomes()->exists() || $category->expenses()->exists(), 409, 'Category is in use');

        $category->delete();
    }
}
