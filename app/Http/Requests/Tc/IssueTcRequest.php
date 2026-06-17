<?php

namespace App\Http\Requests\Tc;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a transfer certificate issue request. reason is required (an empty
 * reason is a 422 per the contract); issue_date is a required date. The
 * one-TC-per-student and branch guards live in the service / route binding.
 */
class IssueTcRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
        ];
    }
}
