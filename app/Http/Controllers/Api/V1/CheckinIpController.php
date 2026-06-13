<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CheckinIp\ListCheckinIpsRequest;
use App\Http\Requests\CheckinIp\StoreCheckinIpRequest;
use App\Http\Requests\CheckinIp\UpdateCheckinIpRequest;
use App\Http\Resources\CheckinIpResource;
use App\Models\CheckinIpWhitelist;
use App\Services\WhitelistService;
use Illuminate\Http\JsonResponse;

class CheckinIpController extends ApiController
{
    public function __construct(private readonly WhitelistService $whitelist) {}

    /**
     * List the calling branch's whitelist entries.
     */
    public function index(ListCheckinIpsRequest $request): JsonResponse
    {
        $entries = $this->whitelist->list($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => CheckinIpResource::collection($entries)->resolve($request),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created whitelist entry.
     */
    public function store(StoreCheckinIpRequest $request): JsonResponse
    {
        $entry = $this->whitelist->create($request->validated());

        return $this->success(CheckinIpResource::make($entry), 'Whitelist entry created', 201);
    }

    /**
     * Update the given whitelist entry (ip/label/is_active).
     */
    public function update(UpdateCheckinIpRequest $request, CheckinIpWhitelist $checkinIp): JsonResponse
    {
        $entry = $this->whitelist->update($checkinIp, $request->validated());

        return $this->success(CheckinIpResource::make($entry), 'Whitelist entry updated');
    }

    /**
     * Remove the given whitelist entry.
     */
    public function destroy(CheckinIpWhitelist $checkinIp): JsonResponse
    {
        $this->whitelist->delete($checkinIp);

        return $this->success(null, 'Whitelist entry deleted');
    }
}
