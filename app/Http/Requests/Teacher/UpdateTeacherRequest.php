<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a teacher profile update. email is intentionally immutable — it is
 * the login identity (users.email) — so any submitted email is rejected.
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($this->route('teacher')->user_id)],
            'designation' => ['required', 'string', 'max:100'],
            'joining_date' => ['nullable', 'date'],
            'email' => ['prohibited'],
        ];
    }
}
