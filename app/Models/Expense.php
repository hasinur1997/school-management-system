<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expense ledger entry (item, price, date, optional category + description).
 * Branch is stamped/scoped automatically via BelongsToBranch. Unlike incomes,
 * expenses have no system-generated rows — every row is editable/deletable.
 */
#[Fillable([
    'branch_id', 'category_id', 'item_name',
    'amount', 'date', 'description', 'created_by',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToBranch, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * The category this expense is grouped under, when set.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
