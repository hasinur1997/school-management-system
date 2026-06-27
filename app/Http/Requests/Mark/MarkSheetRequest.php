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
     * Verify the section belongs to one of the exam's classes and the subject
     * belongs to that same class. The exam is already branch-scoped by
     * route-model binding, and the subject/section lookups carry the branch
     * scope, so foreign ids surface as 422 errors.
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
            $examClassIds = $exam->classIds();

            $section = Section::find($this->integer('section_id'));

            if ($section === null || ! in_array($section->class_id, $examClassIds, true)) {
                $validator->errors()->add('section_id', 'The selected section is not of this exam\'s classes.');

                return;
            }

            $subject = Subject::find($this->integer('subject_id'));

            if ($subject === null || $subject->class_id !== $section->class_id) {
                $validator->errors()->add('subject_id', 'The selected subject is not of this section\'s class.');
            }
        }];
    }
}
