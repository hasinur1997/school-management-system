<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\EntityReportService;
use App\Services\FinanceReportService;
use App\Services\ReportPdfService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 13 reports over the shared report filter contract: the finance reports
 * (Task 13.2 — income, expense, profit-loss) and the entity reports (Task 13.3
 * — students, teachers, assets, fees). All aggregation lives in the report
 * services; the controller resolves the filter and wraps the result in the
 * standard envelope.
 */
class ReportController extends ApiController
{
    public function __construct(
        private readonly FinanceReportService $reports,
        private readonly EntityReportService $entityReports,
        private readonly ReportPdfService $reportPdfs,
    ) {}

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

    /**
     * Student report: totals by status, active head-count by class, and new
     * admissions in the window.
     */
    public function students(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->entityReports->students($request->toFilter()));
    }

    /**
     * Teacher report: totals by status and an attendance summary over the
     * window.
     */
    public function teachers(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->entityReports->teachers($request->toFilter()));
    }

    /**
     * Asset report: total value, by-status breakdown and additions in the
     * window.
     */
    public function assets(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->entityReports->assets($request->toFilter()));
    }

    /**
     * Fees report: invoiced / collected / outstanding totals with by-month and
     * by-class breakdowns.
     */
    public function fees(ReportFilterRequest $request): JsonResponse
    {
        return $this->success($this->entityReports->fees($request->toFilter()));
    }

    /**
     * Stream any of the seven reports as a PDF over the same filter contract.
     * The {type} route constraint rejects unknown types with a 404; filter
     * validation still returns the JSON envelope.
     */
    public function pdf(ReportFilterRequest $request, string $type): Response
    {
        return $this->reportPdfs->render($type, $request->toFilter());
    }
}
