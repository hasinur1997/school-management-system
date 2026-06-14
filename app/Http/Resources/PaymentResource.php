<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "1500.00".
            'amount' => $this->amount,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'status' => $this->invoice->status->value,
                'paid_amount' => $this->invoice->paid_amount,
            ]),
            'receipt_url' => "/api/v1/payments/{$this->id}/receipt",
        ];
    }
}
