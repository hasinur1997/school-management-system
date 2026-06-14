<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Support\Payments\GatewaySession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The response to opening an online checkout: the pending payment's id and
 * transaction reference plus the gateway URL the client redirects to.
 *
 * @property array{payment: Payment, session: GatewaySession} $resource
 */
class OnlinePaymentInitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource['payment'];
        /** @var GatewaySession $session */
        $session = $this->resource['session'];

        return [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'gateway_url' => $session->gatewayUrl,
        ];
    }
}
