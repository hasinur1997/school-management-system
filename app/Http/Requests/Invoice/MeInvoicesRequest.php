<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the self-service invoice read. Both filters are optional: year
 * narrows to one year; student_id names a linked child for parents (ignored for
 * students, who always get their own). The student/parent resolution and
 * linkage check live in the controller.
 */
class MeInvoicesRequest extends FormRequest
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
            'student_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
