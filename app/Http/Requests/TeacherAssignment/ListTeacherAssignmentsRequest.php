<?php

namespace App\Http\Requests\TeacherAssignment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListTeacherAssignmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'teacher_id' => ['sometimes', 'integer', 'min:1'],
            'class_id' => ['sometimes', 'integer', 'min:1'],
            'session_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
