<?php

namespace App\Http\Requests\TeacherAttendance;

use App\Enums\TeacherAttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the teacher-attendance browse filters. All are optional; branch
 * isolation is automatic (teacher_attendances is scoped through the teacher),
 * so these only narrow the in-branch result set.
 */
class ListTeacherAttendanceRequest extends FormRequest
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
            'teacher_id' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date'],
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'status' => ['sometimes', Rule::enum(TeacherAttendanceStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
