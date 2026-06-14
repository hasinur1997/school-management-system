<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the exam browse filters. All are optional; branch isolation is
 * automatic (exams carry their own branch_id), so these only narrow the
 * in-branch result set.
 */
class ListExamsRequest extends FormRequest
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
            'session_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::enum(ExamType::class)],
            'status' => ['sometimes', Rule::enum(ExamStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
