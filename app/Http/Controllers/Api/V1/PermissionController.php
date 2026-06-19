<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PermissionGroupResource;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends ApiController
{
    public function __construct(private readonly PermissionService $permissions) {}

    /**
     * The full assignable permission registry, grouped by module.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success([
            'groups' => PermissionGroupResource::collection($this->permissions->grouped())->resolve($request),
        ]);
    }
}
