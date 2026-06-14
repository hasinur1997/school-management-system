<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates teacher creation. Email and phone must be free across the login
 * (users) table — they become the teacher's credentials — so a clash reports
 * 422 before the create transaction runs instead of hitting a DB constraint.
 */
class StoreTeacherRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email'), Rule::unique('teachers', 'email')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'designation' => ['required', 'string', 'max:100'],
            'joining_date' => ['nullable', 'date'],
            // Non-super-admins cannot choose a branch: any submitted value is
            // ignored and BelongsToBranch stamps their own.
            'branch_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', Rule::exists('branches', 'id')]
                : ['exclude'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->user()->isSuperAdmin() && $this->user()->branch_id === null) {
                    $validator->errors()->add('branch_id', 'Your account is not assigned to a branch.');
                }
            },
        ];
    }

    /**
     * The branch the teacher is stamped with — the caller's own branch, or the
     * requested one for super admins (who have no branch of their own).
     */
    public function targetBranchId(): int
    {
        if ($this->user()->isSuperAdmin()) {
            return $this->integer('branch_id');
        }

        return (int) $this->user()->branch_id;
    }
}
