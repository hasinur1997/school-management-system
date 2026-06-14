<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch isolation for models that have no branch_id of their own but belong
 * to a teacher (teacher_attendances). The teacher carries BranchScope, so
 * requiring the teacher relation to exist hides rows whose teacher is out of
 * branch — they resolve to model-not-found (404), matching the behavior of
 * directly branch-scoped models.
 */
trait BelongsToBranchThroughTeacher
{
    protected static function bootBelongsToBranchThroughTeacher(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            if (! BranchScope::bypassed()) {
                $query->whereHas('teacher');
            }
        });
    }
}
