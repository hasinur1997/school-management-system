<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Expense\ListExpensesRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;

class ExpenseController extends ApiController
{
    public function __construct(private readonly ExpenseService $expenses) {}

    /**
     * Browse expenses in the caller's branch with category/date/search filters.
     */
    public function index(ListExpensesRequest $request): JsonResponse
    {
        $expenses = $this->expenses->list(
            $request->only(['category_id', 'from', 'to', 'search', 'sort', 'direction']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ExpenseResource::collection($expenses)->resolve($request),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }

    /**
     * Create a manual expense.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->validated());

        return $this->success(ExpenseResource::make($expense), 'Expense created', 201);
    }

    /**
     * Update a manual expense. Out-of-branch ids 404 via BranchScope binding.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenses->update($expense, $request->validated());

        return $this->success(ExpenseResource::make($expense), 'Expense updated');
    }

    /**
     * Delete a manual expense.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $this->expenses->delete($expense);

        return $this->success(null, 'Expense deleted');
    }
}
