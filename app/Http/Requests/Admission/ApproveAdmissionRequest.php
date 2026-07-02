<?php

namespace App\Http\Requests\Admission;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the office-use box for converting an application into a student:
 * the academic session, the class (may override the desired class), an
 * optional section (a class may have none), an optional roll number (unique
 * within session+class+section, null section forming its own bucket;
 * auto-assigned as the class's next roll when absent), an optional
 * admission number (auto-generated when absent), and whether to create a linked
 * parent account. Class/section validity is checked branch-scoped in after(),
 * so out-of-branch ids report as invalid rather than leaking other branches.
 */
class ApproveAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Approval creates a linked father parent account unless the office
     * explicitly opts out or chooses another relation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->exists('create_parent_account')) {
            $this->merge(['create_parent_account' => true]);
        }

        if ($this->boolean('create_parent_account') && ! $this->exists('parent_relation')) {
            $this->merge(['parent_relation' => 'father']);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')],
            'class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'roll_no' => [
                'nullable', 'integer', 'min:1', 'max:65535',
                Rule::unique('enrollments', 'roll_no')->where(fn ($query) => $query
                    ->where('session_id', $this->integer('session_id'))
                    ->where('class_id', $this->integer('class_id'))
                    ->where('section_id', $this->input('section_id') === null ? null : $this->integer('section_id'))),
            ],
            'admission_no' => ['nullable', 'string', 'max:30', Rule::unique('students', 'admission_no')],
            'create_parent_account' => ['required', 'boolean'],
            'parent_relation' => ['required_if:create_parent_account,true', Rule::in(['father', 'mother', 'guardian'])],
        ];
    }

    /**
     * Validate the class is visible in the caller's branch and that the
     * section, when given, belongs to it. Both lookups carry the branch scope,
     * so foreign ids are model-not-found and surface as validation errors
     * (422), not 404/leakage.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['class_id', 'section_id'])) {
                return;
            }

            $class = SchoolClass::find($this->integer('class_id'));

            if ($class === null) {
                $validator->errors()->add('class_id', 'The selected class is invalid.');

                return;
            }

            if ($this->input('section_id') === null) {
                return;
            }

            $section = Section::find($this->integer('section_id'));

            if ($section === null || $section->class_id !== $class->id) {
                $validator->errors()->add('section_id', 'The selected section is not of this class.');
            }
        }];
    }
}
