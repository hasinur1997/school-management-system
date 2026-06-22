<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The monthly fee for one (branch, session, class) tuple — the amount invoices
 * copy at generation time. Branch is stamped/scoped automatically via
 * BelongsToBranch; the (branch, session, class) tuple is unique. Editing the
 * fee only affects future invoice generation — existing invoices keep their
 * copied amount.
 */
#[Fillable(['branch_id', 'session_id', 'class_id', 'monthly_fee'])]
class FeeStructure extends Model
{
    /** @use HasFactory<FeeStructureFactory> */
    use BelongsToBranch, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
        ];
    }

    /**
     * The session this fee applies to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * The class this fee applies to.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
