<?php

namespace App\Http\Requests\Teacher;

use App\Enums\TeacherStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates a teacher status flip. Marking inactive cascades to the login
 * (users.is_active = false) and revokes tokens — handled in the service.
 */
class UpdateTeacherStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(TeacherStatus::class)],
        ];
    }
}
