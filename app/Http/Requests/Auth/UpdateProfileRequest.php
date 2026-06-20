<?php

namespace App\Http\Requests\Auth;

use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $teacher = Teacher::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->user()->id)
            ->first();

        $emailRules = [
            $teacher === null ? 'nullable' : 'required',
            'email',
            'max:150',
            Rule::unique('users', 'email')->ignore($this->user()->id),
        ];

        if ($teacher !== null) {
            $emailRules[] = Rule::unique('teachers', 'email')->ignore($teacher->id);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => $emailRules,
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($this->user()->id)],
        ];
    }
}
