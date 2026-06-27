<?php

namespace App\Http\Requests\Parent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a bulk parent trash action. `ids` is a list of parent public ids;
 * the service resolves them branch-scoped, so foreign ids are skipped.
 */
class BulkParentsRequest extends FormRequest
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
