<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Branch\ListBranchesRequest;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class BranchController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListBranchesRequest $request): JsonResponse
    {
        $branches = Branch::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->validated('search') ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => BranchResource::collection($branches)->resolve($request),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
                'last_page' => $branches->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return $this->success(BranchResource::make($branch), 'Branch created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch): JsonResponse
    {
        return $this->success(BranchResource::make($branch));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return $this->success(BranchResource::make($branch), 'Branch updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        try {
            $branch->delete();
        } catch (QueryException) {
            return $this->error('Branch is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Branch deleted');
    }
}
