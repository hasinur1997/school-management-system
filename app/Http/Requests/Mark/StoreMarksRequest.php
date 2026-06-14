<?php

namespace App\Http\Requests\Mark;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a bulk marks save for one subject of one exam. A published exam is
 * frozen (409) before anything else; otherwise the subject must belong to the
 * exam's class, every enrollment must be an active member of that class, and
 * each obtained mark must fall within 0..subject.full_marks (keyed per row at
 * errors.marks.N.obtained_marks). The teacher-assignment check (403) lives in
 * MarkService, not here.
 */
class StoreMarksRequest extends FormRequest
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
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'marks.*.obtained_marks' => ['required', 'numeric'],
        ];
    }

    /**
     * A published exam is frozen; subject must belong to the exam's class; each
     * enrollment must be active in that class; each mark within range. All
     * lookups carry the branch scope, so foreign rows are indistinguishable
     * from invalid ones.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Exam $exam */
            $exam = $this->route('exam');

            // Published exams are frozen regardless of payload validity.
            abort_if($exam->status === ExamStatus::Published, 409, 'Marks are frozen for published exams');

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subject = Subject::find($this->integer('subject_id'));

            if ($subject === null || $subject->class_id !== $exam->class_id) {
                $validator->errors()->add('subject_id', 'The selected subject is not of this exam\'s class.');

                return;
            }

            $activeEnrollmentIds = Enrollment::query()
                ->where('class_id', $exam->class_id)
                ->where('status', EnrollmentStatus::Active)
                ->pluck('id')
                ->all();

            foreach ($this->validated('marks') as $index => $row) {
                if (! in_array($row['enrollment_id'], $activeEnrollmentIds, true)) {
                    $validator->errors()->add(
                        "marks.{$index}.enrollment_id",
                        'The selected enrollment is not an active member of this class.',
                    );

                    continue;
                }

                if ($row['obtained_marks'] < 0 || $row['obtained_marks'] > $subject->full_marks) {
                    $validator->errors()->add(
                        "marks.{$index}.obtained_marks",
                        "Marks must be between 0 and {$subject->full_marks}.",
                    );
                }
            }
        }];
    }
}
