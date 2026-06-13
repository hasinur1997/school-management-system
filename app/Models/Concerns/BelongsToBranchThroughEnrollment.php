<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch isolation for models that have no branch_id of their own but belong
 * to an enrollment (student_attendances). The enrollment's student carries
 * BranchScope, so requiring the enrollment.student chain to exist hides rows
 * whose student is out of branch — they resolve to model-not-found (404),
 * matching the behavior of directly branch-scoped models.
 */
trait BelongsToBranchThroughEnrollment
{
    protected static function bootBelongsToBranchThroughEnrollment(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            if (! BranchScope::bypassed()) {
                $query->whereHas('enrollment.student');
            }
        });
    }
}
