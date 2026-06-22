<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughEnrollment;
use App\Models\Concerns\HasPublicId;
use Database\Factories\MarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single student's mark for one subject in one exam. Grade and grade point
 * are snapshotted from the grading scale at entry, so later scale edits never
 * change stored marks. The branch derives through the enrollment's student (no
 * local branch_id), so isolation comes from BelongsToBranchThroughEnrollment.
 * Unique per (exam_id, enrollment_id, subject_id), so re-entry updates the row.
 */
#[Fillable(['exam_id', 'enrollment_id', 'subject_id', 'obtained_marks', 'grade', 'grade_point', 'entered_by'])]
class Mark extends Model
{
    /** @use HasFactory<MarkFactory> */
    use BelongsToBranchThroughEnrollment, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obtained_marks' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    /**
     * Get the exam this mark belongs to.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the enrollment this mark belongs to. Required by
     * BelongsToBranchThroughEnrollment for branch scoping.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the subject this mark is for.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the user (teacher/admin) who entered this mark.
     */
    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
