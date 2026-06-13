<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains every query on a branch-scoped model to the authenticated
 * user's branch. Bypassed for super admins, who instead filter explicitly
 * via the optional branch_id convention (see AcademicStructureService),
 * and in unauthenticated contexts (console seeders/commands, public
 * endpoints), which handle branches explicitly.
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (self::bypassed()) {
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), Auth::user()->branch_id);
    }

    /**
     * Whether branch isolation is off for the current context: no auth
     * user (console, public routes) or a super admin.
     */
    public static function bypassed(): bool
    {
        $user = Auth::user();

        return $user === null || $user->isSuperAdmin();
    }
}
