<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single role-aware dashboard endpoint (Task 14.2). The shape of `data`
 * depends on the caller's role; all assembly and caching live in the service.
 */
class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Role-aware summary for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->dashboard->for($request->user()));
    }
}
