<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'invoice_no' => $this->invoice_no,
            'student' => [
                'id' => $this->student->id,
                'name_en' => $this->student->name_en,
            ],
            'month' => $this->month,
            'year' => $this->year,
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "1500.00".
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toDateString(),
            // Payments arrive in Task 10.3+; the detail view exposes the field
            // now (empty until then) to match the API contract.
            'payments' => [],
        ];
    }
}
