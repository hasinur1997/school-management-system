<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the exam browse filters. All are optional; branch isolation is
 * automatic for non-super-admins (exams carry their own branch_id). Super
 * admins may narrow to one branch via `branch_id` (see FiltersByBranch).
 */
class ListExamsRequest extends FormRequest
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
            'session_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::enum(ExamType::class)],
            'status' => ['sometimes', Rule::enum(ExamStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ...$this->branchFilterRules(),
        ];
    }
}
