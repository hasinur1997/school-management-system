<?php

namespace App\Http\Requests\Teacher;

use App\Enums\TeacherStatus;
use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates the teacher index filters: status, free-text search, sort column
 * and direction, and pagination. Super admins may narrow to one branch via
 * `branch_id` (see FiltersByBranch).
 */
class ListTeachersRequest extends FormRequest
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
            'status' => ['sometimes', new Enum(TeacherStatus::class)],
            'search' => ['sometimes', 'string', 'max:150'],
            'sort' => ['sometimes', Rule::in(['name', 'joining_date'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ...$this->branchFilterRules(),
        ];
    }
}
