<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\GradingScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of the single global grading scale: a marks range mapping to a
 * grade letter and grade point. The scale has no branch_id — it is shared
 * across every branch and replaced wholesale via PUT /grading-scales.
 */
#[Fillable(['grade', 'min_marks', 'max_marks', 'grade_point', 'is_fail'])]
class GradingScale extends Model
{
    /** @use HasFactory<GradingScaleFactory> */
    use HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_marks' => 'integer',
            'max_marks' => 'integer',
            'grade_point' => 'decimal:2',
            'is_fail' => 'boolean',
        ];
    }
}
