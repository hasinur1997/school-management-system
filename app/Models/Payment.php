<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A payment against an invoice. Branch is stamped/scoped via BelongsToBranch so
 * reads and {payment} bindings isolate per branch (out-of-branch → 404). The
 * settle pipeline (PaymentService::settle) drives a payment to `paid`, stamping
 * receipt_no and paid_at and posting the linked income row.
 */
#[Fillable([
    'branch_id', 'invoice_id', 'receipt_no', 'amount', 'method',
    'status', 'transaction_id', 'gateway_payload', 'paid_at', 'collected_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The invoice this payment settles.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The user who recorded the payment (staff for cash, payer for online).
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * The income row this payment generated on settlement.
     */
    public function income(): HasOne
    {
        return $this->hasOne(Income::class);
    }
}
