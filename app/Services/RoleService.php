<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manages the permission bundles on the six seeded roles. Roles themselves are
 * never created or deleted here; only which permissions they grant changes.
 * The super_admin role is immutable (it bypasses every check anyway).
 */
class RoleService
{
    /**
     * Every role with its permissions and user count, for the list endpoint.
     *
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        // loadCount (on already-loaded models, which carry guard_name) rather
        // than withCount — spatie's polymorphic users() relation can't be built
        // from the empty instance withCount uses.
        return Role::query()
            ->with('permissions')
            ->orderBy('id')
            ->get()
            ->loadCount('users');
    }

    /**
     * A single role loaded for the detail/sync response (permissions + count).
     */
    public function load(Role $role): Role
    {
        return $role->load('permissions')->loadCount('users');
    }

    /**
     * Replace a role's entire permission set. The super_admin role cannot be
     * edited (403). The spatie permission cache is flushed so effective
     * permissions of assigned users update immediately.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        abort_if($role->name === 'super_admin', 403, 'The super admin role cannot be modified');

        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->load($role);
    }
}
