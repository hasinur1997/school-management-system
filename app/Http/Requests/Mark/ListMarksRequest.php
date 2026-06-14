<?php

namespace App\Http\Requests\Mark;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the marks browse filters. All are optional; branch isolation is
 * automatic (marks are branch-scoped through the enrollment), so these only
 * narrow the in-branch result set for the bound exam.
 */
class ListMarksRequest extends FormRequest
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
            'subject_id' => ['sometimes', 'integer'],
            'section_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
