<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\FiltersByBranch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the dashboard request. The only input is the optional super-admin
 * `branch_id` filter (see FiltersByBranch); every other view is derived from
 * the authenticated user.
 */
class DashboardRequest extends FormRequest
{
    use FiltersByBranch;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->branchFilterRules();
    }
}
