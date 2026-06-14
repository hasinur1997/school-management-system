<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamStatus;
use App\Models\Exam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an exam update. Only name/dates/status are editable —
 * session/class/type are immutable (prohibited → 422). A published exam is
 * frozen (→ 409), and the status may never regress to an earlier lifecycle
 * stage (→ 422). Publishing itself is a separate flow (Task 8.1), so the
 * generic update cannot set status to `published`.
 */
class UpdateExamRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            // Lifecycle stage may move forward to upcoming/ongoing/completed;
            // `published` is reached only via the dedicated publish flow (8.1).
            'status' => ['sometimes', Rule::enum(ExamStatus::class)->except([ExamStatus::Published])],
            // Identity columns are immutable.
            'session_id' => ['prohibited'],
            'class_id' => ['prohibited'],
            'type' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Exam $exam */
                $exam = $this->route('exam');

                // A published exam is frozen — this is a conflict (409), not a
                // validation error, and must short-circuit the status checks
                // below (whose comparison would otherwise read as a regression).
                if ($exam->status === ExamStatus::Published) {
                    abort(409, 'Published exams cannot be modified');
                }

                if ($validator->errors()->has('status') || ! $this->filled('status')) {
                    return;
                }

                $target = ExamStatus::from($this->input('status'));

                if ($target->rank() < $exam->status->rank()) {
                    $validator->errors()->add('status', 'Exam status cannot move backwards.');
                }
            },
        ];
    }
}
