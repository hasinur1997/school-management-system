<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the counter-payment body. The permission (fee.collect) is enforced
 * by route middleware; the amount-vs-outstanding and partial-payment rules are
 * stateful (they depend on the invoice + setting) and live in PaymentService,
 * which raises 422 keyed on `amount`.
 */
class LocalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2'],
        ];
    }
}
