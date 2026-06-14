<?php

namespace App\Http\Requests\Mark;

use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the marks entry sheet query: a subject and section, both belonging
 * to the exam's class. Subject/section validity is checked branch-scoped in
 * after(), so out-of-branch ids report as invalid (422) rather than leaking
 * other branches' rows.
 */
class MarkSheetRequest extends FormRequest
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
            'subject_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
        ];
    }

    /**
     * Verify the subject and section both belong to the exam's class. The exam
     * is already branch-scoped by route-model binding, and the subject/section
     * lookups carry the branch scope, so foreign ids surface as 422 errors.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Exam $exam */
            $exam = $this->route('exam');

            $subject = Subject::find($this->integer('subject_id'));

            if ($subject === null || $subject->class_id !== $exam->class_id) {
                $validator->errors()->add('subject_id', 'The selected subject is not of this exam\'s class.');
            }

            $section = Section::find($this->integer('section_id'));

            if ($section === null || $section->class_id !== $exam->class_id) {
                $validator->errors()->add('section_id', 'The selected section is not of this exam\'s class.');
            }
        }];
    }
}
