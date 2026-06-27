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
 * session/class/type are immutable (prohibited → 422). Every exam (including
 * published ones) may have its name/dates edited. The status may never regress
 * to an earlier lifecycle stage (→ 422), and publishing itself is a separate
 * flow (Task 8.1), so the generic update cannot set status to `published`.
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
            'class_ids' => ['prohibited'],
            'all_classes' => ['prohibited'],
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
