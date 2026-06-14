<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shared income/expense category. Branch is stamped/scoped automatically via
 * BelongsToBranch; the (branch, name, type) tuple is unique. Income and expense
 * rows reference a category optionally — a category in use cannot be deleted.
 */
#[Fillable(['branch_id', 'name', 'type'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    /**
     * Income rows grouped under this category.
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
