<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Income;
use App\Support\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The income / expense / profit-loss reports (Task 13.2). Every figure is
 * computed in SQL (SUM + GROUP BY) — there is no PHP-side summation — and each
 * report shares one shape: a resolved filters block, a headline total, a
 * by-category breakdown (LEFT JOIN categories, null → "Uncategorized") and a
 * day- or month-grained series. When the filter is consolidated (super admin,
 * no single branch) a per-branch breakdown is appended.
 */
class FinanceReportService
{
    /**
     * Income report: total + by-category + series for the resolved window.
     *
     * @return array<string, mixed>
     */
    public function income(ReportFilter $filter): array
    {
        return $this->ledgerReport(Income::class, $filter);
    }

    /**
     * Expense report: identical shape to {@see Income()}, over the expense
     * ledger.
     *
     * @return array<string, mixed>
     */
    public function expense(ReportFilter $filter): array
    {
        return $this->ledgerReport(Expense::class, $filter);
    }

    /**
     * Profit-loss report: income vs expense totals, their net (may be
     * negative), and a combined { date, income, expense } series.
     *
     * @return array<string, mixed>
     */
    public function profitLoss(ReportFilter $filter): array
    {
        $incomeTotal = (string) $this->scoped(Income::class, $filter)->sum('amount');
        $expenseTotal = (string) $this->scoped(Expense::class, $filter)->sum('amount');

        $data = [
            'filters' => $this->filtersBlock($filter),
            'income_total' => $this->money($incomeTotal),
            'expense_total' => $this->money($expenseTotal),
            'net' => $this->money(bcsub($incomeTotal, $expenseTotal, 2)),
            'series' => $this->combinedSeries($filter),
        ];

        if ($filter->branchId === null) {
            $data['by_branch'] = $this->profitLossByBranch($filter);
        }

        return $data;
    }

    /**
     * Shared income/expense report builder.
     *
     * @param  class-string<Income|Expense>  $model
     * @return array<string, mixed>
     */
    private function ledgerReport(string $model, ReportFilter $filter): array
    {
        $data = [
            'filters' => $this->filtersBlock($filter),
            'total' => $this->money((string) $this->scoped($model, $filter)->sum('amount')),
            'by_category' => $this->byCategory($model, $filter),
            'series' => $this->series($model, $filter),
        ];

        if ($filter->branchId === null) {
            $data['by_branch'] = $this->byBranch($model, $filter);
        }

        return $data;
    }

    /**
     * A base query for the model, restricted to the resolved date window and,
     * for a single-branch filter, to that branch. A null branch is the
     * consolidated view: branch isolation is already bypassed for super admins,
     * so the query spans every branch.
     *
     * @param  class-string<Income|Expense>  $model
     */
    private function scoped(string $model, ReportFilter $filter): Builder
    {
        [$start, $end] = $filter->range();
        $query = $model::query();
        $table = $query->getModel()->getTable();

        $query->whereBetween("{$table}.date", [$start->toDateString(), $end->toDateString()]);

        if ($filter->branchId !== null) {
            $query->where("{$table}.branch_id", $filter->branchId);
        }

        return $query;
    }

    /**
     * Per-category totals, LEFT JOINing categories so uncategorised rows fall
     * under "Uncategorized". Ordered by amount, largest first.
     *
     * @param  class-string<Income|Expense>  $model
     * @return array<int, array<string, string>>
     */
    private function byCategory(string $model, ReportFilter $filter): array
    {
        $table = (new $model)->getTable();
        $label = $this->coalesce('categories.name', 'Uncategorized');

        return $this->scoped($model, $filter)
            ->leftJoin('categories', "{$table}.category_id", '=', 'categories.id')
            ->selectRaw("{$label} as category_name, SUM({$table}.amount) as total_amount")
            ->groupByRaw($label)
            ->orderByRaw('total_amount desc')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_name,
                'amount' => $this->money((string) $row->total_amount),
            ])
            ->all();
    }

    /**
     * Per-branch totals for the consolidated view, ordered by amount.
     *
     * @param  class-string<Income|Expense>  $model
     * @return array<int, array<string, string>>
     */
    private function byBranch(string $model, ReportFilter $filter): array
    {
        $table = (new $model)->getTable();

        return $this->scoped($model, $filter)
            ->join('branches', "{$table}.branch_id", '=', 'branches.id')
            ->selectRaw("branches.name as branch_name, SUM({$table}.amount) as total_amount")
            ->groupBy('branches.id', 'branches.name')
            ->orderByRaw('total_amount desc')
            ->get()
            ->map(fn ($row) => [
                'branch' => (string) $row->branch_name,
                'amount' => $this->money((string) $row->total_amount),
            ])
            ->all();
    }

    /**
     * Time series at the filter's granularity (daily ≤ 62 days, else monthly),
     * grouped and summed in SQL, ascending by date.
     *
     * @param  class-string<Income|Expense>  $model
     * @return array<int, array<string, string>>
     */
    private function series(string $model, ReportFilter $filter): array
    {
        $table = (new $model)->getTable();
        $bucket = $this->dateBucket("{$table}.date", $filter->granularity());

        return $this->scoped($model, $filter)
            ->selectRaw("{$bucket} as bucket, SUM({$table}.amount) as total_amount")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->bucket,
                'amount' => $this->money((string) $row->total_amount),
            ])
            ->all();
    }

    /**
     * Combined income/expense series for profit-loss: one entry per bucket with
     * both sums (zero where a side has no rows), ascending by date.
     *
     * @return array<int, array<string, string>>
     */
    private function combinedSeries(ReportFilter $filter): array
    {
        $income = collect($this->series(Income::class, $filter))->keyBy('date');
        $expense = collect($this->series(Expense::class, $filter))->keyBy('date');

        return $income->keys()
            ->merge($expense->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $date) => [
                'date' => $date,
                'income' => $income[$date]['amount'] ?? $this->money('0'),
                'expense' => $expense[$date]['amount'] ?? $this->money('0'),
            ])
            ->all();
    }

    /**
     * Per-branch income/expense/net for the consolidated profit-loss view.
     *
     * @return array<int, array<string, string>>
     */
    private function profitLossByBranch(ReportFilter $filter): array
    {
        $income = collect($this->byBranch(Income::class, $filter))->keyBy('branch');
        $expense = collect($this->byBranch(Expense::class, $filter))->keyBy('branch');

        return $income->keys()
            ->merge($expense->keys())
            ->unique()
            ->values()
            ->map(function (string $branch) use ($income, $expense) {
                $in = $income[$branch]['amount'] ?? $this->money('0');
                $out = $expense[$branch]['amount'] ?? $this->money('0');

                return [
                    'branch' => $branch,
                    'income' => $in,
                    'expense' => $out,
                    'net' => $this->money(bcsub($in, $out, 2)),
                ];
            })
            ->all();
    }

    /**
     * The resolved filters echoed back to the caller.
     *
     * @return array<string, string>
     */
    private function filtersBlock(ReportFilter $filter): array
    {
        [$start, $end] = $filter->range();

        return [
            'period' => $filter->period->value,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'branch' => $filter->branchId === null
                ? 'All Branches'
                : (Branch::find($filter->branchId)?->name ?? 'All Branches'),
        ];
    }

    /**
     * The driver-appropriate date bucket expression for the series GROUP BY:
     * a calendar day for daily granularity, the first of the month otherwise.
     */
    private function dateBucket(string $column, string $granularity): string
    {
        if ($granularity === 'daily') {
            return "DATE({$column})";
        }

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-01', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m-01')";
    }

    /**
     * A driver-agnostic COALESCE producing the given fallback string literal.
     */
    private function coalesce(string $column, string $fallback): string
    {
        return "COALESCE({$column}, '{$fallback}')";
    }

    /**
     * Normalise a numeric/string amount to a fixed two-decimal string.
     */
    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
