<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends ApiController
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * Return the effective global + branch settings. Secrets are masked as
     * `{ "is_set": bool }`. Super admins may target a branch via ?branch_id=.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->settings->effective($this->resolveBranchId($request)));
    }

    /**
     * Bulk upsert settings, then return the refreshed effective set. Unknown
     * keys or type mismatches are rejected (422) before reaching here.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $branchId = $request->resolvedBranchId();

        $this->settings->upsert($request->validated('settings'), $branchId);

        return $this->success($this->settings->effective($branchId), 'Settings updated');
    }

    /**
     * The public, unauthenticated subset for the admission page. No secrets.
     */
    public function publicSettings(): JsonResponse
    {
        return $this->success($this->settings->publicSettings());
    }

    /**
     * Resolve the branch context for a read: the caller's branch, or for a
     * super admin the optional ?branch_id= (null when omitted).
     */
    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->isSuperAdmin() ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }
}
