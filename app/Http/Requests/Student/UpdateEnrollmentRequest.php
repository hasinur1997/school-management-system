<?php

namespace App\Http\Requests\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an edit to a single enrollment row (a student's class history).
 * Structural 422 checks live here (project convention: the Form Request owns
 * 422, the service owns runtime guards): the session must exist, the class must
 * be in-branch, the section must belong to that class, and no *other*
 * enrollment may already hold the requested roll in the target section for the
 * target session (duplicate roll → 422). The public-id middleware has already
 * resolved session_id/class_id/section_id to integer keys by this point.
 */
class UpdateEnrollmentRequest extends FormRequest
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
            'section_id' => ['required', 'integer'],
            'roll_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', Rule::enum(EnrollmentStatus::class)],
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

                if ($errors->has('class_id') || $errors->has('section_id')) {
                    return;
                }

                // Class must exist within the caller's branch (the branch-scoped
                // model means an unknown/out-of-branch class → 422, not a leak).
                $class = SchoolClass::find($this->integer('class_id'));

                if ($class === null) {
                    $errors->add('class_id', 'The selected class is invalid.');

                    return;
                }

                // The section must belong to that class (branch-scoped) — any
                // other pairing is a 422 on section_id.
                $sectionValid = Section::query()
                    ->whereKey($this->integer('section_id'))
                    ->where('class_id', $class->id)
                    ->exists();

                if (! $sectionValid) {
                    $errors->add('section_id', 'The selected section does not belong to the class.');

                    return;
                }

                // Duplicate roll: no *other* enrollment may already hold this
                // roll in the target section for the target session.
                if ($errors->has('roll_no')) {
                    return;
                }

                $enrollment = $this->route('enrollment');

                $duplicateRoll = Enrollment::query()
                    ->where('session_id', $this->integer('session_id'))
                    ->where('section_id', $this->integer('section_id'))
                    ->where('roll_no', $this->integer('roll_no'))
                    ->when(
                        $enrollment instanceof Enrollment,
                        fn ($query) => $query->whereKeyNot($enrollment->getKey())
                    )
                    ->exists();

                if ($duplicateRoll) {
                    $errors->add('roll_no', 'This roll number is already taken in the target section.');
                }
            },
        ];
    }
}
