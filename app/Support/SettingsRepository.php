<?php

namespace App\Support;

use App\Services\SettingService;
use Illuminate\Support\Facades\Auth;

/**
 * Typed accessor for tunable settings consumed across services. Reads the
 * database-backed SettingService (Task 14.1) and falls back to config defaults
 * when a value is unset, so callers always get a usable value. Branch-scoped
 * accessors resolve the branch from their argument, then the authenticated user.
 */
class SettingsRepository
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * The time of day (HH:MM, app timezone) after which a teacher check-in is
     * marked `late` rather than `present`. Defaults to 09:00.
     */
    public function teacherLateThreshold(?int $branchId = null): string
    {
        return $this->settings->get('teacher_late_threshold', $this->branch($branchId))
            ?? config('attendance.teacher_late_threshold', '09:00');
    }

    /**
     * The day of the month a generated monthly invoice falls due. Defaults to
     * the 10th.
     */
    public function invoiceDueDay(?int $branchId = null): int
    {
        return (int) ($this->settings->get('invoice_due_day', $this->branch($branchId))
            ?? config('fees.invoice_due_day', 10));
    }

    /**
     * Whether a counter payment may settle an invoice partially. When false,
     * cash collection requires the full outstanding amount. Defaults to false.
     */
    public function partialPaymentEnabled(?int $branchId = null): bool
    {
        return (bool) ($this->settings->get('partial_payment_enabled', $this->branch($branchId))
            ?? config('fees.partial_payment_enabled', false));
    }

    /**
     * SSLCommerz store id used to open checkout sessions and validate callbacks.
     */
    public function sslcommerzStoreId(): ?string
    {
        return $this->settings->get('sslcommerz_store_id')
            ?? config('services.sslcommerz.store_id');
    }

    /**
     * SSLCommerz store password paired with the store id.
     */
    public function sslcommerzStorePassword(): ?string
    {
        return $this->settings->get('sslcommerz_store_password')
            ?? config('services.sslcommerz.store_password');
    }

    /**
     * Whether SSLCommerz runs against the sandbox host (true) or live (false).
     * Defaults to sandbox.
     */
    public function sslcommerzSandbox(): bool
    {
        $value = $this->settings->get('sslcommerz_sandbox');

        return $value === null
            ? (bool) config('services.sslcommerz.sandbox', true)
            : (bool) $value;
    }

    /**
     * Resolve the branch context for a branch-scoped lookup: the explicit
     * argument, then the authenticated user's branch.
     */
    private function branch(?int $branchId): ?int
    {
        return $branchId ?? Auth::user()?->branch_id;
    }
}
