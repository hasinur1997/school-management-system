<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Asset\ListAssetsRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;

class AssetController extends ApiController
{
    public function __construct(private readonly AssetService $assets) {}

    /**
     * Browse assets in the caller's branch with status/search filters.
     */
    public function index(ListAssetsRequest $request): JsonResponse
    {
        $assets = $this->assets->list(
            $request->only(['status', 'search', 'sort', 'direction']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => AssetResource::collection($assets)->resolve($request),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    /**
     * At-a-glance totals: total_value (in_use + damaged), count, by_status.
     */
    public function summary(): JsonResponse
    {
        return $this->success($this->assets->summary(), 'OK');
    }

    /**
     * Create an asset (status defaults to in_use).
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        $asset = $this->assets->create($request->validated());

        return $this->success(AssetResource::make($asset), 'Asset created', 201);
    }

    /**
     * Update an asset. Out-of-branch ids 404 via BranchScope binding.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $asset = $this->assets->update($asset, $request->validated());

        return $this->success(AssetResource::make($asset), 'Asset updated');
    }

    /**
     * Delete an asset.
     */
    public function destroy(Asset $asset): JsonResponse
    {
        $this->assets->delete($asset);

        return $this->success(null, 'Asset deleted');
    }
}
