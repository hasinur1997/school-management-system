<?php

namespace App\Http\Requests\Attendance;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the attendance entry sheet query: the class and its section are
 * required, and the date defaults to today. Class/section validity is checked
 * branch-scoped in after(), so out-of-branch ids report as invalid (422)
 * rather than leaking other branches' rows.
 */
class AttendanceSheetRequest extends FormRequest
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
            'class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'date' => ['sometimes', 'date'],
        ];
    }

    /**
     * Verify the class is visible in the caller's branch and the section belongs
     * to it. Both lookups carry the branch scope, so foreign ids are
     * model-not-found and surface as 422 validation errors, not leakage.
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

            $section = Section::find($this->integer('section_id'));

            if ($section === null || $section->class_id !== $class->id) {
                $validator->errors()->add('section_id', 'The selected section is not of this class.');
            }
        }];
    }
}
