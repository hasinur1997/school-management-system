<?php

namespace App\Services;

use App\Support\ReportFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams any of the seven Phase 13 reports (Task 13.4) as a PDF. The figures
 * come from the exact same services as the JSON endpoints (Tasks 13.2/13.3), so
 * a PDF and its JSON counterpart over the same filter carry identical data; this
 * class only chooses the source method, a title and the per-type Blade partial.
 */
class ReportPdfService
{
    /**
     * type => [document title, partial under pdf.partials.reports]. income and
     * expense share the ledger partial; the rest have their own.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const REPORTS = [
        'income' => ['Income Report', 'ledger'],
        'expense' => ['Expense Report', 'ledger'],
        'profit-loss' => ['Profit &amp; Loss Report', 'profit-loss'],
        'students' => ['Student Report', 'students'],
        'teachers' => ['Teacher Report', 'teachers'],
        'assets' => ['Asset Report', 'assets'],
        'fees' => ['Fees Report', 'fees'],
    ];

    public function __construct(
        private readonly FinanceReportService $finance,
        private readonly EntityReportService $entity,
    ) {}

    /**
     * Build and stream the PDF for one report type over the resolved filter.
     * The route constraint guarantees a known type, so the lookups never miss.
     */
    public function render(string $type, ReportFilter $filter): Response
    {
        [$title, $partial] = self::REPORTS[$type];
        $data = $this->data($type, $filter);
        $filters = $data['filters'];

        $pdf = Pdf::loadView('pdf.report', [
            'title' => $title,
            'partial' => $partial,
            'data' => $data,
            'filters' => $filters,
        ]);

        return $pdf->stream("report-{$type}-{$filters['from']}-{$filters['to']}.pdf");
    }

    /**
     * Delegate to the report service that owns each type — the same call the
     * JSON endpoint makes.
     *
     * @return array<string, mixed>
     */
    private function data(string $type, ReportFilter $filter): array
    {
        return match ($type) {
            'income' => $this->finance->income($filter),
            'expense' => $this->finance->expense($filter),
            'profit-loss' => $this->finance->profitLoss($filter),
            'students' => $this->entity->students($filter),
            'teachers' => $this->entity->teachers($filter),
            'assets' => $this->entity->assets($filter),
            'fees' => $this->entity->fees($filter),
        };
    }
}
