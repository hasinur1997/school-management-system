<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's fee invoice for a single month. Generated monthly from the
 * student's class fee structure, copying the amount so a later fee edit never
 * alters an issued invoice. Branch is stamped/scoped automatically via
 * BelongsToBranch; the (student, month, year) tuple is unique, which makes
 * monthly generation idempotent.
 */
#[Fillable([
    'branch_id', 'student_id', 'enrollment_id', 'invoice_no',
    'month', 'year', 'amount', 'paid_amount', 'status', 'due_date',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToBranch, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * The student this invoice bills.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The enrollment (class+section+session) the invoice was generated from.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Payments recorded against this invoice (newest first when loaded).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
