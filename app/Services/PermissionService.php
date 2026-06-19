<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Reads the seeded permission registry from spatie and groups it by module
 * (the prefix before the first dot) for the assignment UI. The registry is
 * fixed by the seeders; this service never creates or deletes permissions.
 */
class PermissionService
{
    /**
     * The full registry grouped by module, modules and permissions both in
     * name order.
     *
     * @return Collection<int, array{module: string, permissions: Collection<int, Permission>}>
     */
    public function grouped(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'))
            ->map(fn (Collection $permissions, string $module): array => [
                'module' => $module,
                'permissions' => $permissions->values(),
            ])
            ->values();
    }
}
