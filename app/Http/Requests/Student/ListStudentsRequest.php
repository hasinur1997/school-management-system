<?php

namespace App\Http\Requests\Student;

use App\Enums\StudentStatus;
use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates the student index filters: class/section/session scope, status, a
 * free-text search across name/admission_no/father_mobile, and pagination.
 * Super admins may narrow to one branch via `branch_id` (see FiltersByBranch).
 */
class ListStudentsRequest extends FormRequest
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
            'class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'section_id' => ['sometimes', 'integer', 'exists:sections,id'],
            'session_id' => ['sometimes', 'integer', 'exists:academic_sessions,id'],
            'status' => ['sometimes', new Enum(StudentStatus::class)],
            'search' => ['sometimes', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ...$this->branchFilterRules(),
        ];
    }
}
