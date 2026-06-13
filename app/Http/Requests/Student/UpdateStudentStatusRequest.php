<?php

namespace App\Http\Requests\Student;

use App\Enums\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a student status flip. Only active/inactive are allowed here; the
 * `tc` status is owned by the TC module and is rejected with a pointed message.
 */
class UpdateStudentStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in([StudentStatus::Active->value, StudentStatus::Inactive->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Use the TC module to issue a transfer certificate.',
        ];
    }
}
