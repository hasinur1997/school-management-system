<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughEnrollment;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ExamResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted per-exam result snapshot for one enrollment: total marks, GPA
 * (average of subject grade points), overall grade and pass flag. Generated
 * repeatably until publication, which stamps published_at and freezes the row
 * against later grading-scale edits. The branch derives through the
 * enrollment's student (no local branch_id), so isolation comes from
 * BelongsToBranchThroughEnrollment. Unique per (exam_id, enrollment_id).
 */
#[Fillable(['exam_id', 'enrollment_id', 'total_marks', 'gpa', 'grade', 'is_passed', 'published_at'])]
class ExamResult extends Model
{
    /** @use HasFactory<ExamResultFactory> */
    use BelongsToBranchThroughEnrollment, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'gpa' => 'decimal:2',
            'is_passed' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the exam this result belongs to.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
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
