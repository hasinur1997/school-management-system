<?php

namespace App\Http\Requests\SchoolClass;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClassesRequest extends FormRequest
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
     * Super admin filtering convention: `?branch_id=` narrows the list to
     * one existing branch, `all` (or omitting it) returns every branch.
     * For everyone else BranchScope governs and the input is ignored.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (! $this->user()->isSuperAdmin()) {
            return ['branch_id' => ['exclude']];
        }

        if ($this->input('branch_id') === 'all') {
            return ['branch_id' => ['in:all']];
        }

        return ['branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')]];
    }

    /**
     * The branch filter for the service: a branch id, or null for all.
     */
    public function branchFilter(): ?int
    {
        $value = $this->validated('branch_id');

        return $value === null || $value === 'all' ? null : (int) $value;
    }
}
