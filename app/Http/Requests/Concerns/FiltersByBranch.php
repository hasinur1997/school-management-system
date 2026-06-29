<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Super-admin branch filtering for list requests.
 *
 * BranchScope (see App\Models\Scopes\BranchScope) does not constrain super
 * admins, so they pass an explicit `?branch_id=` to narrow a listing to one
 * branch; `all` (or omitting it) returns every branch. For everyone else the
 * input is excluded and BranchScope governs automatically.
 *
 * Public branch ids are resolved to internal ids by the ResolvePublicIds
 * middleware before validation runs.
 */
trait FiltersByBranch
{
    /**
     * Validation rules for the optional `branch_id` filter. Spread these into
     * the request's own rules().
     *
     * @return array<string, mixed>
     */
    protected function branchFilterRules(): array
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            return ['branch_id' => ['exclude']];
        }

        if ($this->input('branch_id') === 'all') {
            return ['branch_id' => ['in:all']];
        }

        return ['branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')]];
    }

    /**
     * The resolved branch filter: a branch id, or null for all branches / a
     * non-super-admin caller (whose branch_id is excluded above).
     */
    public function branchFilter(): ?int
    {
        $value = $this->validated('branch_id');

        return $value === null || $value === 'all' ? null : (int) $value;
    }

    /**
     * Merge the resolved branch filter into a service filter array, adding
     * `branch_id` only when a super admin narrowed to one branch.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function withBranchFilter(array $filters): array
    {
        $branchId = $this->branchFilter();

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        return $filters;
    }
}
