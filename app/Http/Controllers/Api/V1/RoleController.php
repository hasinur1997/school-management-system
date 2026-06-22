<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends ApiController
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * List the six roles with their permissions and user counts.
     */
    public function index(): JsonResponse
    {
        return $this->success(RoleResource::collection($this->roles->list()));
    }

    /**
     * Show a single role with its permissions. Unknown id 404s via binding.
     */
    public function show(Role $role): JsonResponse
    {
        return $this->success(RoleResource::make($this->roles->load($role)));
    }

    /**
     * Replace a role's permission set. Editing super_admin → 403; unknown
     * permission name → 422 (validated in the request).
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roles->syncPermissions($role, $request->validated('permissions'));

        return $this->success(RoleResource::make($role), 'Permissions updated');
    }
}
