<?php

namespace App\Http\Requests\Mark;

use App\Models\Exam;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the multi-subject marks matrix query: a class that the exam covers,
 * and an optional section to narrow the roster to. Every subject of that class
 * forms the grid's columns; the roster is the class's active enrollments (all
 * sections) unless a section is given. Branch-scoped lookups mean a foreign
 * class/section surfaces as a 422 rather than leaking another branch's roster.
 */
class MarkMatrixRequest extends FormRequest
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
            'class_id' => ['required', 'integer'],
            'section_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * The class must be one the exam covers; an optional section must belong to
     * that class.
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

            $classId = $this->integer('class_id');

            if (! in_array($classId, $exam->classIds(), true)) {
                $validator->errors()->add('class_id', 'The selected class is not of this exam\'s classes.');

                return;
            }

            if ($this->filled('section_id')) {
                $section = Section::find($this->integer('section_id'));

                if ($section === null || $section->class_id !== $classId) {
                    $validator->errors()->add('section_id', 'The selected section is not of this class.');
                }
            }
        }];
    }

    /**
     * The optional section filter, or null for the whole class.
     */
    public function sectionId(): ?int
    {
        return $this->filled('section_id') ? $this->integer('section_id') : null;
    }
}
