<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates exam creation. The class must exist within the caller's branch
 * (checked through the branch-scoped model so out-of-branch ids report 422
 * rather than leak); the (session, class, type) tuple must be unique. The
 * exam's branch_id is derived from the resolved class in the service.
 */
class StoreExamRequest extends FormRequest
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
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(ExamType::class)],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                // Class must exist within the caller's branch. Skip when class_id
                // or type already failed format validation.
                if ($errors->has('class_id') || $errors->has('type') || $errors->has('session_id')) {
                    return;
                }

                if (SchoolClass::find($this->integer('class_id')) === null) {
                    $errors->add('class_id', 'The selected class is invalid.');

                    return;
                }

                $duplicate = Exam::query()
                    ->where('session_id', $this->integer('session_id'))
                    ->where('class_id', $this->integer('class_id'))
                    ->where('type', $this->input('type'))
                    ->exists();

                if ($duplicate) {
                    $errors->add('type', 'This exam already exists for the class');
                }
            },
        ];
    }
}
