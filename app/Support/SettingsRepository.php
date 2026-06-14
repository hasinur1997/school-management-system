<?php

namespace App\Support;

/**
 * Stub source for tunable settings. Reads config defaults today; Task 14.1
 * replaces the body with a database-backed lookup (the settings table) without
 * touching callers.
 */
class SettingsRepository
{
    /**
     * The time of day (HH:MM, app timezone) after which a teacher check-in is
     * marked `late` rather than `present`. Defaults to 09:00.
     */
    public function teacherLateThreshold(): string
    {
        return config('attendance.teacher_late_threshold', '09:00');
    }

    /**
     * The day of the month a generated monthly invoice falls due. Defaults to
     * the 10th.
     */
    public function invoiceDueDay(): int
    {
        return (int) config('fees.invoice_due_day', 10);
    }

    /**
     * Whether a counter payment may settle an invoice partially. When false,
     * cash collection requires the full outstanding amount. Defaults to false.
     */
    public function partialPaymentEnabled(): bool
    {
        return (bool) config('fees.partial_payment_enabled', false);
    }

    /**
     * SSLCommerz store id used to open checkout sessions and validate callbacks.
     */
    public function sslcommerzStoreId(): ?string
    {
        return config('services.sslcommerz.store_id');
    }

    /**
     * SSLCommerz store password paired with the store id.
     */
    public function sslcommerzStorePassword(): ?string
    {
        return config('services.sslcommerz.store_password');
    }

    /**
     * Whether SSLCommerz runs against the sandbox host (true) or live (false).
     * Defaults to sandbox.
     */
    public function sslcommerzSandbox(): bool
    {
        return (bool) config('services.sslcommerz.sandbox', true);
    }
}
