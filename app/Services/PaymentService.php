<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the payment settlement pipeline. settle() is the single transaction
 * reused verbatim by counter collection (10.3) and the SSLCommerz IPN (10.5):
 * payment → paid (receipt_no, paid_at), invoice paid_amount/status updated, and
 * exactly one linked income row posted.
 */
class PaymentService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Record a counter (cash) payment against an invoice and settle it. The
     * amount must equal the outstanding balance unless partial payment is
     * enabled, in which case any amount up to the outstanding balance is
     * accepted.
     *
     * @throws ValidationException
     */
    public function collectLocal(Invoice $invoice, string $amount, User $collector): Payment
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            abort(409, 'Invoice is already paid');
        }

        $outstanding = bcsub($invoice->amount, $invoice->paid_amount, 2);

        $this->assertAcceptableAmount($amount, $outstanding);

        return DB::transaction(function () use ($invoice, $amount, $collector): Payment {
            $payment = Payment::create([
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => PaymentMethod::Cash,
                'status' => PaymentStatus::Pending,
                'collected_by' => $collector->id,
            ]);

            return $this->settle($payment);
        });
    }

    /**
     * THE settlement pipeline (one transaction): mark the payment paid with a
     * receipt number and paid_at, roll the amount into the invoice (paid or
     * partial), and post exactly one linked income row. Reused by the IPN.
     */
    public function settle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $invoice = $payment->invoice()->lockForUpdate()->first();

            $paidAt = now();

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => $paidAt,
                'receipt_no' => $this->receiptNo($invoice, $payment),
            ])->save();

            $newPaid = bcadd($invoice->paid_amount, $payment->amount, 2);

            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'status' => bccomp($newPaid, $invoice->amount, 2) >= 0
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::Partial,
            ])->save();

            $payment->income()->create([
                'branch_id' => $invoice->branch_id,
                'payment_id' => $payment->id,
                'title' => sprintf(
                    'Monthly fee %d/%d — %s',
                    $invoice->month,
                    $invoice->year,
                    $invoice->invoice_no,
                ),
                'amount' => $payment->amount,
                'date' => $paidAt->toDateString(),
                'created_by' => $payment->collected_by,
            ]);

            return $payment->setRelation('invoice', $invoice);
        });
    }

    /**
     * Validate the requested amount against the outstanding balance and the
     * partial-payment setting. Throws a 422 keyed on `amount` on rejection.
     *
     * @throws ValidationException
     */
    private function assertAcceptableAmount(string $amount, string $outstanding): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero',
            ]);
        }

        if ($this->settings->partialPaymentEnabled()) {
            if (bccomp($amount, $outstanding, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Amount may not exceed the outstanding {$outstanding}",
                ]);
            }

            return;
        }

        if (bccomp($amount, $outstanding, 2) !== 0) {
            throw ValidationException::withMessages([
                'amount' => "Full payment of {$outstanding} required",
            ]);
        }
    }

    /**
     * Build the receipt number RCPT-{branchCode}-{seq}; the payment id is the
     * per-branch sequence (unique, race-safe via the auto-increment).
     */
    private function receiptNo(Invoice $invoice, Payment $payment): string
    {
        return sprintf('RCPT-%s-%06d', $invoice->branch->code, $payment->id);
    }
}
