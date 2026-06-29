<?php

namespace App\Http\Requests\Admission;

use App\Enums\AdmissionStatus;
use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates the admission review index filters: optional status, desired
 * class, a created-date range, free-text search, and pagination. Super admins
 * may narrow to one branch via `branch_id` (see FiltersByBranch).
 */
class ListAdmissionsRequest extends FormRequest
{
    use FiltersByBranch;

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
            'status' => ['sometimes', new Enum(AdmissionStatus::class)],
            'desired_class_id' => ['sometimes', 'integer'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'search' => ['sometimes', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ...$this->branchFilterRules(),
        ];
    }
}
