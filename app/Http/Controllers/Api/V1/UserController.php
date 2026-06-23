<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Resources\UserAccountResource;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Http\JsonResponse;

class UserController extends ApiController
{
    public function __construct(private readonly UserAccessService $users) {}

    /**
     * List user accounts with their roles for assignment. Branch-scoped,
     * paginated, filterable by search (name/email/phone) and role name.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = $this->users->list(
            $request->only(['search', 'role']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => UserAccountResource::collection($users)->resolve($request),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Replace a user's role set. Unknown role → 422; stripping the last active
     * super admin → 422; unknown user id → 404 via binding.
     */
    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $user = $this->users->syncRoles($user, $request->validated('roles'));

        return $this->success(UserAccountResource::make($user), 'Roles updated');
    }

    /**
     * Show one user account with its roles, for the profile view linked from
     * "recorded by" on the attendance roster. Unknown id → 404 via binding.
     */
    public function show(User $user): JsonResponse
    {
        return $this->success(
            UserAccountResource::make($user->load('roles')),
            'OK',
        );
    }
}
