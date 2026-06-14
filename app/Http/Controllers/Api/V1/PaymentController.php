<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Payment\LocalPaymentRequest;
use App\Http\Requests\Payment\OnlinePaymentRequest;
use App\Http\Resources\OnlinePaymentInitResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Record a counter (cash) payment against an invoice and settle it. Guarded
     * by fee.collect; out-of-branch {invoice} ids 404 via BranchScope binding.
     * 409 when the invoice is already paid; 422 (errors.amount) on an amount that
     * doesn't satisfy the outstanding/partial rules.
     */
    public function local(LocalPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->payments->collectLocal(
            $invoice,
            (string) $request->input('amount'),
            $request->user(),
        );

        return $this->success(
            PaymentResource::make($payment),
            'Payment recorded',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Open an SSLCommerz checkout for an invoice: create a pending payment and
     * return the gateway URL for client redirect. Authorized via
     * StudentPolicy::payOnline (staff fee.collect / self / linked parent); a
     * denial hides existence (404). Out-of-branch {invoice} ids 404 via
     * BranchScope binding. 409 when the invoice is already paid; 422
     * (errors.amount) on a bad amount; 502 when the gateway is unreachable
     * (payment marked failed).
     */
    public function online(OnlinePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        if ($request->user()->cannot('payOnline', $invoice->student)) {
            abort(404);
        }

        $init = $this->payments->initOnline(
            $invoice,
            $request->filled('amount') ? (string) $request->input('amount') : null,
            $request->user(),
        );

        return $this->success(
            OnlinePaymentInitResource::make($init),
            'Checkout session created',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Stream the money receipt PDF for a paid payment. Authorized via
     * StudentPolicy::viewInvoices (staff invoice.view / self / linked parent);
     * a denial or a non-paid payment hides existence (404). Out-of-branch
     * {id} 404s via BranchScope.
     */
    public function receipt(Request $request, int $id): Response
    {
        $payment = Payment::query()
            ->with([
                'invoice.student:id,name_en,user_id',
                'invoice.enrollment.schoolClass:id,name',
                'invoice.enrollment.section:id,name',
                'branch:id,name,address,phone',
                'collector:id,name',
            ])
            ->findOrFail($id);

        if ($payment->status !== PaymentStatus::Paid) {
            abort(404);
        }

        if ($request->user()->cannot('viewInvoices', $payment->invoice->student)) {
            abort(404);
        }

        return Pdf::loadView('pdf.receipt', ['payment' => $payment])
            ->stream("receipt-{$payment->receipt_no}.pdf");
    }

    /**
     * SSLCommerz IPN — the server-to-server callback that is the source of
     * truth. Public (no auth: BranchScope is bypassed, so the payment is found
     * globally by transaction id). Idempotent: a replayed IPN for a settled
     * payment is a no-op. Unknown tran_id → 422 (logged); validation/amount
     * mismatch → payment failed + 422; valid first call → settle + 200.
     */
    public function ipn(Request $request): JsonResponse
    {
        $tranId = (string) $request->input('tran_id');

        $payment = Payment::query()
            ->where('transaction_id', $tranId)
            ->first();

        if ($payment === null) {
            Log::warning('SSLCommerz IPN for unknown transaction id.', ['tran_id' => $tranId]);

            return $this->error('Unknown transaction', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->payments->settleFromIpn($payment, $request->all());

        if ($result['status'] === 'failed') {
            return $this->error('Payment validation failed', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success($result);
    }

    /**
     * SSLCommerz browser-redirect landing (success / fail / cancel). Public and
     * read-only — the IPN drives all state changes; this only reports the
     * payment's current status so the frontend can poll/redirect. No DB writes.
     * Unknown tran_id → 404.
     */
    public function landing(Request $request): JsonResponse
    {
        $payment = Payment::query()
            ->where('transaction_id', (string) $request->input('tran_id'))
            ->firstOrFail();

        return $this->success([
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
        ]);
    }
}
