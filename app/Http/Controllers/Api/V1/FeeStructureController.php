<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\FeeStructure\ListFeeStructuresRequest;
use App\Http\Requests\FeeStructure\StoreFeeStructureRequest;
use App\Http\Requests\FeeStructure\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use App\Services\FeeStructureService;
use Illuminate\Http\JsonResponse;

class FeeStructureController extends ApiController
{
    public function __construct(private readonly FeeStructureService $feeStructures) {}

    /**
     * Browse fee structures in the caller's branch, filtered and paginated.
     */
    public function index(ListFeeStructuresRequest $request): JsonResponse
    {
        $feeStructures = $this->feeStructures->list(
            $request->only(['session_id', 'class_id']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => FeeStructureResource::collection($feeStructures)->resolve($request),
            'meta' => [
                'current_page' => $feeStructures->currentPage(),
                'per_page' => $feeStructures->perPage(),
                'total' => $feeStructures->total(),
                'last_page' => $feeStructures->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new fee structure.
     */
    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        $feeStructure = $this->feeStructures->create($request->validated());

        return $this->success(FeeStructureResource::make($feeStructure), 'Fee structure created', 201);
    }

    /**
     * Update a fee structure's monthly amount. Out-of-branch ids 404 via
     * BranchScope binding.
     */
    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure = $this->feeStructures->update($feeStructure, $request->validated());

        return $this->success(FeeStructureResource::make($feeStructure), 'Fee structure updated');
    }
}
