<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Income\ListIncomesRequest;
use App\Http\Requests\Income\StoreIncomeRequest;
use App\Http\Requests\Income\UpdateIncomeRequest;
use App\Http\Resources\IncomeResource;
use App\Models\Income;
use App\Services\IncomeService;
use Illuminate\Http\JsonResponse;

class IncomeController extends ApiController
{
    public function __construct(private readonly IncomeService $incomes) {}

    /**
     * Browse incomes in the caller's branch with category/date/search filters.
     */
    public function index(ListIncomesRequest $request): JsonResponse
    {
        $incomes = $this->incomes->list(
            $request->only(['category_id', 'from', 'to', 'search', 'sort', 'direction']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => IncomeResource::collection($incomes)->resolve($request),
            'meta' => [
                'current_page' => $incomes->currentPage(),
                'per_page' => $incomes->perPage(),
                'total' => $incomes->total(),
                'last_page' => $incomes->lastPage(),
            ],
        ]);
    }

    /**
     * Create a manual income.
     */
    public function store(StoreIncomeRequest $request): JsonResponse
    {
        $income = $this->incomes->create($request->validated());

        return $this->success(IncomeResource::make($income), 'Income created', 201);
    }

    /**
     * Update a manual income. System-generated rows → 403. Out-of-branch ids
     * 404 via BranchScope binding.
     */
    public function update(UpdateIncomeRequest $request, Income $income): JsonResponse
    {
        $income = $this->incomes->update($income, $request->validated());

        return $this->success(IncomeResource::make($income), 'Income updated');
    }

    /**
     * Delete a manual income. System-generated rows → 403.
     */
    public function destroy(Income $income): JsonResponse
    {
        $this->incomes->delete($income);

        return $this->success(null, 'Income deleted');
    }
}
