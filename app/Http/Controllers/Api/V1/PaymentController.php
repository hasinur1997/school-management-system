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
}
