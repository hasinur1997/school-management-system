<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughEnrollment;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AnnualResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted annual result snapshot for one enrollment: the three published
 * per-exam GPAs, the weighted annual GPA (0.25·S1 + 0.25·S2 + 0.50·Final),
 * overall grade and pass flag. Generated repeatably until publication, which
 * stamps published_at and freezes the row against later grading-scale edits.
 * The branch derives through the enrollment's student (no local branch_id), so
 * isolation comes from BelongsToBranchThroughEnrollment. Unique per enrollment.
 */
#[Fillable([
    'enrollment_id',
    'first_semester_gpa',
    'second_semester_gpa',
    'final_exam_gpa',
    'annual_gpa',
    'grade',
    'is_passed',
    'published_at',
])]
class AnnualResult extends Model
{
    /** @use HasFactory<AnnualResultFactory> */
    use BelongsToBranchThroughEnrollment, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_semester_gpa' => 'decimal:2',
            'second_semester_gpa' => 'decimal:2',
            'final_exam_gpa' => 'decimal:2',
            'annual_gpa' => 'decimal:2',
            'is_passed' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the enrollment this result belongs to. Required by
     * BelongsToBranchThroughEnrollment for branch scoping.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
