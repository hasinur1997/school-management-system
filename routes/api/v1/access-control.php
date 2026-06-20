<?php

use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Access control management (15.1): super-admin-only surface to read the
// permission registry, edit role permission bundles, and assign roles to
// users. Gated by role.manage — seeded into no role bundle, so only super
// admins reach it (via Gate::before). Service-layer guards protect the
// super_admin role (403) and the last active super admin (422).
Route::middleware(['auth:sanctum', 'permission:role.manage'])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles.sync');
});
