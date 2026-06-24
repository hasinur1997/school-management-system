<?php

namespace App\Http\Requests\Student;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates direct student creation — the office path that mints a student
 * without an admission application. It carries the full profile (the same
 * mutable set as UpdateStudentRequest), the initial enrollment (session/class/
 * section/roll), an optional admission number (auto-generated when absent), and
 * the optional linked-parent box mirroring ApproveAdmissionRequest. Class and
 * section validity is checked branch-scoped in after(), so out-of-branch ids
 * report as invalid rather than leaking other branches. The *_id fields arrive
 * as public-id hashes and are resolved to integer keys by ResolvePublicIds
 * before validation, so they validate as integers here.
 */
class StoreStudentRequest extends FormRequest
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
            'name_bn' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],

            'father_name_bn' => ['required', 'string', 'max:150'],
            'father_name_en' => ['required', 'string', 'max:150'],
            'father_nid' => ['nullable', 'string', 'max:20'],

            'mother_name_bn' => ['required', 'string', 'max:150'],
            'mother_name_en' => ['required', 'string', 'max:150'],
            'mother_nid' => ['nullable', 'string', 'max:20'],

            'present_village' => ['required', 'string', 'max:100'],
            'present_post_office' => ['required', 'string', 'max:100'],
            'present_upazila' => ['required', 'string', 'max:100'],
            'present_district' => ['required', 'string', 'max:100'],
            'present_division' => ['required', 'string', 'max:100'],

            'permanent_village' => ['required', 'string', 'max:100'],
            'permanent_post_office' => ['required', 'string', 'max:100'],
            'permanent_upazila' => ['required', 'string', 'max:100'],
            'permanent_district' => ['required', 'string', 'max:100'],
            'permanent_division' => ['required', 'string', 'max:100'],

            'father_mobile' => ['required', 'string', 'max:20'],
            'mother_mobile' => ['nullable', 'string', 'max:20'],

            'birth_reg_no' => ['required', 'string', 'max:25', Rule::unique('students', 'birth_reg_no')],
            'date_of_birth' => ['required', 'date'],
            'religion' => ['required', 'string', 'max:50'],
            'nationality' => ['required', 'string', 'max:50'],
            'caste' => ['nullable', 'string', 'max:50'],

            // Initial enrollment. roll_no is unique within session+class+section.
            'session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')],
            'class_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'roll_no' => [
                'required', 'integer', 'min:1', 'max:65535',
                Rule::unique('enrollments', 'roll_no')->where(fn ($query) => $query
                    ->where('session_id', $this->integer('session_id'))
                    ->where('class_id', $this->integer('class_id'))
                    ->where('section_id', $this->integer('section_id'))),
            ],

            // Auto-generated per branch+year when absent.
            'admission_no' => ['nullable', 'string', 'max:30', Rule::unique('students', 'admission_no')],

            'create_parent_account' => ['required', 'boolean'],
            'parent_relation' => ['required_if:create_parent_account,true', Rule::in(['father', 'mother', 'guardian'])],

            // Non-super-admins cannot choose a branch: any submitted value is
            // ignored and the caller's own branch is used.
            'branch_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', Rule::exists('branches', 'id')]
                : ['exclude'],
        ];
    }

    /**
     * Validate the class is visible in the caller's branch and that the section
     * belongs to it. Both lookups carry the branch scope, so foreign ids are
     * model-not-found and surface as validation errors (422), not 404/leakage.
     * Also confirm a non-super-admin actually has a branch.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->user()->isSuperAdmin() && $this->user()->branch_id === null) {
                $validator->errors()->add('branch_id', 'Your account is not assigned to a branch.');

                return;
            }

            if ($validator->errors()->hasAny(['class_id', 'section_id'])) {
                return;
            }

            $class = SchoolClass::find($this->integer('class_id'));

            if ($class === null) {
                $validator->errors()->add('class_id', 'The selected class is invalid.');

                return;
            }

            $section = Section::find($this->integer('section_id'));

            if ($section === null || $section->class_id !== $class->id) {
                $validator->errors()->add('section_id', 'The selected section is not of this class.');
            }
        }];
    }

    /**
     * The branch the student is stamped with — the caller's own branch, or the
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
