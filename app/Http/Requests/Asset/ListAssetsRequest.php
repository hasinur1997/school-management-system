<?php

namespace App\Http\Requests\Asset;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the asset browse filters. All optional; branch isolation is
 * automatic (assets carry their own branch_id). Sort is restricted to
 * value|purchase_date.
 */
class ListAssetsRequest extends FormRequest
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
            'status' => ['sometimes', 'in:in_use,damaged,disposed'],
            'search' => ['sometimes', 'string', 'max:150'],
            'sort' => ['sometimes', 'in:value,purchase_date'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
