<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\FiltersByBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the attendance entry sheet query: the class is required, the
 * section is optional (omitted, the roster spans the whole class), and the
 * date defaults to today. Class/section validity is checked branch-scoped in
 * after(), so out-of-branch ids report as invalid (422) rather than leaking
 * other branches' rows. Super admins bypass BranchScope, so their selected
 * branch (`branch_id`, see FiltersByBranch) is checked against the class
 * explicitly.
 */
class AttendanceSheetRequest extends FormRequest
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
        return [
            'class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')],
            'section_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sections', 'id')],
            'date' => ['sometimes', 'date'],
            ...$this->branchFilterRules(),
        ];
    }

    /**
     * Verify the class is visible in the caller's branch (for super admins,
     * the explicitly selected branch) and the section — when given — belongs
     * to it. Both lookups carry the branch scope, so foreign ids are
     * model-not-found and surface as 422 validation errors, not leakage.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['class_id', 'section_id', 'branch_id'])) {
                return;
            }

            $class = SchoolClass::find($this->integer('class_id'));

            if ($class === null) {
                $validator->errors()->add('class_id', 'The selected class is invalid.');

                return;
            }

            // branchFilter() reads validated() which is not available mid
            // validation, so resolve the (already rule-checked) input directly.
            $user = $this->user();
            $raw = $this->input('branch_id');
            $branchId = $user !== null && $user->isSuperAdmin() && $raw !== null && $raw !== 'all'
                ? (int) $raw
                : null;

            if ($branchId !== null && (int) $class->branch_id !== $branchId) {
                $validator->errors()->add('class_id', 'The selected class is invalid.');

                return;
            }

            if (! $this->filled('section_id')) {
                return;
            }

            $section = Section::find($this->integer('section_id'));

            if ($section === null || $section->class_id !== $class->id) {
                $validator->errors()->add('section_id', 'The selected section is not of this class.');
            }
        }];
    }
}
