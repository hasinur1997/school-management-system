<?php

namespace App\Http\Requests\Promotion;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates an individual promotion. The structural 422 checks live here
 * (project convention: Form Request owns 422, the service owns the 403/409
 * runtime guards): the student must be in-branch, the target session/class/
 * section must exist, the target section must belong to the target class, and
 * no other enrollment may already hold the requested roll in the target
 * section/session (duplicate roll → 422). The "student has not passed" override
 * gate (403) and the "already enrolled in target session" guard (409) are
 * raised in PromotionService::individual.
 */
class IndividualPromotionRequest extends FormRequest
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
            'student_id' => ['required', 'integer'],
            'to_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'to_class_id' => ['required', 'integer'],
            'to_section_id' => ['required', 'integer'],
            'roll_no' => ['required', 'integer', 'min:1'],
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

                // Student must exist within the caller's branch (the branch-scoped
                // model means an unknown/out-of-branch student → 422, not a leak).
                if (! $errors->has('student_id') && Student::find($this->integer('student_id')) === null) {
                    $errors->add('student_id', 'The selected student is invalid.');
                }

                if ($errors->has('to_class_id') || $errors->has('to_section_id')) {
                    return;
                }

                // The target class must be in-branch and the target section must
                // belong to it (branch-scoped) — any other pairing is a 422.
                $class = SchoolClass::find($this->integer('to_class_id'));

                if ($class === null) {
                    $errors->add('to_class_id', 'The selected class is invalid.');

                    return;
                }

                $sectionValid = Section::query()
                    ->whereKey($this->integer('to_section_id'))
                    ->where('class_id', $class->id)
                    ->exists();

                if (! $sectionValid) {
                    $errors->add('to_section_id', 'The selected section does not belong to the class.');

                    return;
                }

                // Duplicate roll: no other enrollment may already hold this roll
                // in the target section for the target session.
                if ($errors->has('roll_no')) {
                    return;
                }

                $duplicateRoll = Enrollment::query()
                    ->where('session_id', $this->integer('to_session_id'))
                    ->where('section_id', $this->integer('to_section_id'))
                    ->where('roll_no', $this->integer('roll_no'))
                    ->exists();

                if ($duplicateRoll) {
                    $errors->add('roll_no', 'This roll number is already taken in the target section.');
                }
            },
        ];
    }
}
