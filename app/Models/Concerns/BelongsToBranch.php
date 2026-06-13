<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Branch isolation for models with a branch_id column. Queries are
 * constrained to the auth user's branch (out-of-branch records resolve to
 * model-not-found, hence 404), and creates are stamped from the auth user —
 * any submitted branch_id is ignored for non-super-admins. Super admins
 * bypass both and must provide branch_id explicitly on create.
 */
trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if (! BranchScope::bypassed()) {
                $model->setAttribute('branch_id', Auth::user()->branch_id);
            }
        });
    }

    /**
     * Get the branch the model belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
