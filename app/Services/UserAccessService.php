<?php

namespace App\Services;

use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lists user accounts and assigns roles to them for the access-control UI.
 * Listing applies the shared BranchScope to the query (not globally on the
 * model — User must stay unscoped for auth/token resolution); a super admin —
 * the only caller while role.manage is super-admin-only — sees all branches.
 */
class UserAccessService
{
    /**
     * Browse user accounts with their roles, filtered by a free-text search
     * over name/email/phone and an optional role name. The BranchScope is
     * applied to this query so a non-super-admin sees only their own branch.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->withGlobalScope(BranchScope::class, new BranchScope)
            ->with('roles')
            ->when(isset($filters['role']), fn (Builder $query) => $query->role($filters['role']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Replace a user's role set. Stripping super_admin from the last active
     * super admin is blocked (422 lockout guard). The spatie permission cache
     * is flushed so the user's effective permissions update immediately.
     *
     * @param  array<int, string>  $roles
     *
     * @throws ValidationException
     */
    public function syncRoles(User $user, array $roles): User
    {
        $this->guardLastSuperAdmin($user, $roles);

        $user->syncRoles($roles);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->load('roles');
    }

    /**
     * Reject removing super_admin from the only active super admin left.
     *
     * @param  array<int, string>  $roles
     *
     * @throws ValidationException
     */
    private function guardLastSuperAdmin(User $user, array $roles): void
    {
        $removingSuperAdmin = $user->hasRole('super_admin') && ! in_array('super_admin', $roles, true);

        if (! $removingSuperAdmin) {
            return;
        }

        $activeSuperAdmins = User::query()
            ->role('super_admin')
            ->where('is_active', true)
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'At least one super admin is required',
            ]);
        }
    }
}
