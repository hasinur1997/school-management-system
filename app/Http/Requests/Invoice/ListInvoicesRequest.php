<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the invoice browse filters. All are optional; branch isolation is
 * automatic (invoices carry their own branch_id), so these only narrow the
 * in-branch result set.
 */
class ListInvoicesRequest extends FormRequest
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
            'student_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
