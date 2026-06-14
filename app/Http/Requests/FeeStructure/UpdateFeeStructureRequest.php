<?php

namespace App\Http\Requests\FeeStructure;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a fee-structure update. Only monthly_fee is editable —
 * session/class are immutable (prohibited → 422). Updating the amount affects
 * only future invoice generation; existing invoices keep their copied amount.
 */
class UpdateFeeStructureRequest extends FormRequest
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
            // Money: non-negative, at most 2 decimal places (3dp → 422).
            'monthly_fee' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            // Identity columns are immutable.
            'session_id' => ['prohibited'],
            'class_id' => ['prohibited'],
        ];
    }
}
