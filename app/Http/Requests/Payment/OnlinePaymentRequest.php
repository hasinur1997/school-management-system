<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the online-checkout body. The amount is optional (defaults to the
 * invoice's outstanding balance); the policy check (student self / linked parent
 * / staff with fee.collect) and the amount-vs-outstanding rules are stateful and
 * live in the controller and PaymentService respectively.
 */
class OnlinePaymentRequest extends FormRequest
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
            'amount' => ['sometimes', 'numeric', 'decimal:0,2'],
        ];
    }
}
