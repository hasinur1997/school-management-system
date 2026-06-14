<?php

namespace App\Http\Requests\FeeStructure;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the fee-structure browse filters. All are optional; branch
 * isolation is automatic (fee structures carry their own branch_id), so these
 * only narrow the in-branch result set.
 */
class ListFeeStructuresRequest extends FormRequest
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
            'session_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
