<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch isolation for models that have no branch_id of their own but
 * belong to a class (sections, subjects). The schoolClass() relation
 * carries BranchScope, so requiring its existence hides rows whose class
 * is out of branch — they resolve to model-not-found (404), matching the
 * behavior of directly branch-scoped models.
 */
trait BelongsToBranchThroughClass
{
    protected static function bootBelongsToBranchThroughClass(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            if (! BranchScope::bypassed()) {
                $query->whereHas('schoolClass');
            }
        });
    }
}
