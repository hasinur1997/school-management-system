<?php

namespace App\Http\Requests\Asset;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates asset creation. value must be non-negative; purchase_date and
 * description are optional; status defaults to in_use when omitted.
 * branch_id/created_by are stamped server-side, never accepted from input.
 */
class StoreAssetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'value' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'purchase_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:in_use,damaged,disposed'],
        ];
    }
}
