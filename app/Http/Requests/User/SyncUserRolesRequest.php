<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape-validates the desired role set for a user sync. Each name must be one
 * of the six seeded roles. Authorization is the route middleware's job
 * (permission:role.manage); this request only validates the payload. The
 * last-super-admin lockout guard lives in the service.
 */
class SyncUserRolesRequest extends FormRequest
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
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(['super_admin', 'admin', 'accountant', 'teacher', 'student', 'parent'])],
        ];
    }
}
