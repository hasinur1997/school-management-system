<?php

namespace App\Http\Requests\CheckinIp;

use App\Rules\IpOrCidr;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCheckinIpRequest extends FormRequest
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
            'ip_address' => [
                'required',
                'string',
                'max:45',
                new IpOrCidr,
                Rule::unique('checkin_ip_whitelists', 'ip_address')->where('branch_id', $this->targetBranchId()),
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
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
     * The branch the entry is stamped with — the caller's own branch, or the
     * requested one for super admins (who have no branch of their own).
     */
    public function targetBranchId(): ?int
    {
        if ($this->user()->isSuperAdmin()) {
            return $this->integer('branch_id');
        }

        return $this->user()->branch_id;
    }
}
