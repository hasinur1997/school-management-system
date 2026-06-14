<?php

namespace App\Jobs;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies the payer that an online (SSLCommerz) payment settled. Dispatched
 * after commit from the IPN handler once the payment is paid. Idempotent: it
 * only reads the settled payment and mails a confirmation, so a replay (which
 * never re-queues — the IPN no-ops a settled payment) would be harmless anyway.
 */
class SendPaymentReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying, escalating per attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly Payment $payment) {}

    /**
     * Execute the job: email the payer a payment confirmation.
     *
     * Students and parents authenticate by phone and carry no email address;
     * their confirmation is delivered out-of-band (SMS, out of scope), so the
     * job is a no-op for them — mirroring SendCredentials.
     */
    public function handle(): void
    {
        $payer = $this->payment->collector;

        if ($payer === null || $payer->email === null) {
            return;
        }

        $invoice = $this->payment->invoice;

        Mail::to($payer->email)->send(new PaymentReceiptMail(
            name: $payer->name,
            receiptNo: (string) $this->payment->receipt_no,
            amount: (string) $this->payment->amount,
            invoiceNo: (string) $invoice->invoice_no,
        ));
    }
}
