<?php

namespace App\Http\Requests\SchoolClass;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'numeric_level' => [
                'required',
                'integer',
                'between:1,12',
                Rule::unique('school_classes', 'numeric_level')->where('branch_id', $this->targetBranchId()),
            ],
            'branch_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', Rule::exists('branches', 'id')]
                : ['prohibited'],
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
     * The branch the class is stamped with — the caller's own branch, or the
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
