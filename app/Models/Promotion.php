<?php

namespace App\Models;

use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log of one promotion action: the student, the enrollment they moved out of,
 * the enrollment they moved into (null when held back), who promoted them and
 * when. The branch derives through the student (no local branch_id), so no
 * branch trait is needed — every promotion references a branch-scoped student
 * and enrollment.
 */
#[Fillable([
    'student_id',
    'from_enrollment_id',
    'to_enrollment_id',
    'type',
    'promoted_by',
    'promoted_at',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'promoted_at' => 'datetime',
        ];
    }

    /**
     * Get the student this promotion concerns.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the enrollment the student moved out of.
     */
    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'from_enrollment_id');
    }

    /**
     * Get the new enrollment the student moved into (null when held back).
     */
    public function toEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'to_enrollment_id');
    }

    /**
     * Get the user who performed the promotion.
     */
    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
