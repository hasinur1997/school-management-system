<?php

namespace App\Support;

use App\Enums\ReportPeriod;
use App\Models\AcademicSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Immutable description of a report's time window and branch scope, shared by
 * every Phase 13 report endpoint. Built from a validated ReportFilterRequest.
 *
 * The resolved window comes from {@see range()}: the period supplies a default
 * span (current ISO week / calendar month / academic session) and any explicit
 * from/to overrides the matching edge — so a non-custom period with from/to
 * given still honours them. `custom` always carries both edges (the request
 * guarantees it). `branchId` is null for the consolidated, all-branch view
 * (super admin `branch_id=all` or omitted); otherwise it is the single branch
 * the report is scoped to.
 */
final class ReportFilter
{
    public function __construct(
        public readonly ReportPeriod $period,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly ?int $branchId,
    ) {}

    /**
     * The resolved inclusive [start, end] window as day-bounded timestamps.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        [$start, $end] = $this->periodRange();

        if ($this->from !== null) {
            $start = $this->from->startOfDay();
        }

        if ($this->to !== null) {
            $end = $this->to->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * Series granularity for the resolved window: daily for spans of 62 days or
     * fewer, monthly beyond that.
     */
    public function granularity(): string
    {
        [$start, $end] = $this->range();

        return (int) $start->diffInDays($end) <= 62 ? 'daily' : 'monthly';
    }

    /**
     * The default window implied by the period, before from/to overrides.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodRange(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->period) {
            ReportPeriod::Weekly => [
                $now->startOfWeek(CarbonInterface::MONDAY),
                $now->endOfWeek(CarbonInterface::SUNDAY),
            ],
            ReportPeriod::Monthly => [$now->startOfMonth(), $now->endOfMonth()],
            ReportPeriod::Yearly => $this->yearlyRange($now),
            // custom always has both edges overridden by from/to; these bounds
            // are only a placeholder that the request guarantees is replaced.
            ReportPeriod::Custom => [$now->startOfDay(), $now->endOfDay()],
        };
    }

    /**
     * The current academic session's span, or the calendar year when none is
     * marked current.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function yearlyRange(CarbonImmutable $now): array
    {
        $session = AcademicSession::query()->where('is_current', true)->first();

        if ($session !== null) {
            return [
                CarbonImmutable::parse($session->start_date)->startOfDay(),
                CarbonImmutable::parse($session->end_date)->endOfDay(),
            ];
        }

        return [$now->startOfYear(), $now->endOfYear()];
    }
}
