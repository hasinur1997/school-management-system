<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a teacher profile update. The email belongs to the linked users row
 * (login identity), so updates are checked against users.email and mirrored by
 * the service.
 */
class UpdateTeacherRequest extends FormRequest
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
        $teacher = $this->route('teacher');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($teacher->user_id),
                Rule::unique('teachers', 'email')->ignore($teacher),
            ],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($this->route('teacher')->user_id)],
            'designation' => ['required', 'string', 'max:100'],
            'joining_date' => ['nullable', 'date'],
        ];
    }
}
