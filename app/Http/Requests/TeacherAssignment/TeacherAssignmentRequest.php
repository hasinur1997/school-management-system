<?php

namespace App\Http\Requests\TeacherAssignment;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and updating a teacher assignment.
 *
 * Existence of class/section/subject is checked through the models so the
 * branch global scope applies — out-of-branch ids are reported as invalid
 * (422) rather than leaking other branches' rows. teacher_id existence is
 * NOT validated until Task 2.1 adds the teachers table.
 */
abstract class TeacherAssignmentRequest extends FormRequest
{
    /**
     * The assignment id to exclude from the duplicate-tuple check
     * (the row being updated), or null when creating.
     */
    abstract protected function ignoredId(): ?int;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'min:1'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                // Class must exist within the caller's branch. Skip only when
                // class_id itself failed format validation.
                if ($errors->has('class_id')) {
                    return;
                }

                $class = SchoolClass::find($this->integer('class_id'));
                if ($class === null) {
                    $errors->add('class_id', 'The selected class is invalid.');

                    return;
                }

                // Section, when given, must exist and belong to the class.
                if ($this->filled('section_id')) {
                    $section = Section::find($this->integer('section_id'));
                    if ($section === null) {
                        $validator->errors()->add('section_id', 'The selected section is invalid.');
                    } elseif ($section->class_id !== $class->id) {
                        $validator->errors()->add('section_id', 'The section does not belong to the class.');
                    }
                }

                // Subject, when given, must exist and belong to the class.
                if ($this->filled('subject_id')) {
                    $subject = Subject::find($this->integer('subject_id'));
                    if ($subject === null) {
                        $validator->errors()->add('subject_id', 'The selected subject is invalid.');
                    } elseif ($subject->class_id !== $class->id) {
                        $validator->errors()->add('subject_id', 'The subject does not belong to the class.');
                    }
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->isDuplicateTuple()) {
                    $validator->errors()->add('teacher_id', 'This teacher is already assigned to this class/section/subject for the session.');
                }
            },
        ];
    }

    /**
     * Whether an identical assignment tuple already exists. NULL section/subject
     * are matched as NULL (SQL leaves those out of the unique index).
     */
    private function isDuplicateTuple(): bool
    {
        return TeacherAssignment::query()
            ->where('teacher_id', $this->integer('teacher_id'))
            ->where('session_id', $this->integer('session_id'))
            ->where('class_id', $this->integer('class_id'))
            ->where(fn ($query) => $this->filled('section_id')
                ? $query->where('section_id', $this->integer('section_id'))
                : $query->whereNull('section_id'))
            ->where(fn ($query) => $this->filled('subject_id')
                ? $query->where('subject_id', $this->integer('subject_id'))
                : $query->whereNull('subject_id'))
            ->when($this->ignoredId() !== null, fn ($query) => $query->whereKeyNot($this->ignoredId()))
            ->exists();
    }
}
