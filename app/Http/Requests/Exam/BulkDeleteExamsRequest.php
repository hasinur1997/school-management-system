<?php

namespace App\Http\Requests\Exam;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a bulk exam delete. `ids` is a list of exam public ids; the service
 * resolves them branch-scoped, so ids outside the caller's branch are simply
 * skipped rather than leaking existence.
 */
class BulkDeleteExamsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ];
    }
}
