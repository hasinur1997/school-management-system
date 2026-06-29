<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListClassesRequest extends FormRequest
{
    use FiltersByBranch;

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
        return $this->branchFilterRules();
    }
}
