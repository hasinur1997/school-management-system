<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the attendance browse filters. All are optional; branch isolation
 * is automatic (student_attendances is branch-scoped through the enrollment),
 * so these only narrow the in-branch result set. Super admins may narrow to
 * one branch via `branch_id` (see FiltersByBranch).
 */
class ListAttendanceRequest extends FormRequest
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
            'class_id' => ['sometimes', 'integer'],
            'section_id' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::enum(AttendanceStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ...$this->branchFilterRules(),
        ];
    }
}
