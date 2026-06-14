<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Income ledger entry. Task 10.3 only writes the system-generated fee income
 * (one per settled payment, payment_id set → not editable); the CRUD endpoints
 * and branch scoping for manual income arrive in Task 11.2.
 */
#[Fillable([
    'branch_id', 'category_id', 'payment_id', 'title',
    'amount', 'date', 'description', 'created_by',
])]
class Income extends Model
{
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
     * The payment that generated this income, when system-generated.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
