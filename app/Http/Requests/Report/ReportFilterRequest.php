<?php

namespace App\Http\Requests\Report;

use App\Enums\ReportPeriod;
use App\Support\ReportFilter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The shared filter contract for every Phase 13 report endpoint. Validates the
 * period enum and the optional from/to range (custom requires both; from must
 * not exceed to), and resolves the branch scope: super admins may target one
 * branch or `all` (consolidated), everyone else is forced to their own branch
 * and any submitted branch_id is ignored. Produces a {@see ReportFilter}.
 */
class ReportFilterRequest extends FormRequest
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
        $rules = [
            'period' => ['required', Rule::enum(ReportPeriod::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];

        // Branch selection mirrors the list-endpoint convention: super admins
        // may pass a branch id or `all`; for everyone else the input is dropped
        // and the filter is forced to their own branch.
        if (! $this->user()->isSuperAdmin()) {
            $rules['branch_id'] = ['exclude'];
        } elseif ($this->input('branch_id') === 'all') {
            $rules['branch_id'] = ['in:all'];
        } else {
            $rules['branch_id'] = ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->enum('period', ReportPeriod::class) !== ReportPeriod::Custom) {
                    return;
                }

                if ($this->input('from') === null) {
                    $validator->errors()->add('from', 'The from field is required when period is custom.');
                }

                if ($this->input('to') === null) {
                    $validator->errors()->add('to', 'The to field is required when period is custom.');
                }
            },
        ];
    }

    /**
     * The validated filter, ready for the report services.
     */
    public function toFilter(): ReportFilter
    {
        return new ReportFilter(
            period: $this->enum('period', ReportPeriod::class),
            from: $this->resolveDate('from'),
            to: $this->resolveDate('to'),
            branchId: $this->resolvedBranchId(),
        );
    }

    private function resolveDate(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /**
     * The branch to scope the report to: the chosen branch for super admins, or
     * null for the consolidated (`all`/omitted) view; otherwise the caller's own
     * branch, regardless of any submitted value.
     */
    private function resolvedBranchId(): ?int
    {
        if ($this->user()->isSuperAdmin()) {
            $value = $this->validated('branch_id');

            return ($value === null || $value === 'all') ? null : (int) $value;
        }

        return $this->user()->branch_id !== null ? (int) $this->user()->branch_id : null;
    }
}
