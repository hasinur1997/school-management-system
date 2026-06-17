<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;

/**
 * Finance reports (Task 13.2): income, expense and profit-loss, each over the
 * shared report filter contract. All aggregation lives in
 * {@see FinanceReportService}; the controller resolves the filter and wraps the
 * result in the standard envelope.
 */
class ReportController extends ApiController
{
    public function __construct(private readonly FinanceReportService $reports) {}

    /**
     * Income total, by-category breakdown and series for the resolved window.
     */
    public function income(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->reports->income($request->toFilter()));
    }

    /**
     * Expense report — same shape as income.
     */
    public function expense(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->reports->expense($request->toFilter()));
    }

    /**
     * Profit-loss: income/expense totals, net and combined series.
     */
    public function profitLoss(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->reports->profitLoss($request->toFilter()));
    }
}
