<?php

namespace App\Http\Requests\Promotion;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the bulk-promotion request. The structural 422 checks live here
 * (project convention: Form Request owns 422, the service owns the 409 runtime
 * guards): the target session must differ from the source (same session → 422),
 * the source class must be in-branch, and the target section must belong to the
 * resolved next class (numeric_level + 1) — a bad section/class pairing is 422.
 * The annual-results-published and already-promoted guards are 409s raised in
 * PromotionService::bulk.
 */
class BulkPromotionRequest extends FormRequest
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
            'from_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'from_class_id' => ['required', 'integer'],
            // Same session → 422 via `different`.
            'to_session_id' => ['required', 'integer', 'different:from_session_id', 'exists:academic_sessions,id'],
            'to_section_id' => ['required', 'integer'],
            'roll_strategy' => ['required', 'string', 'in:by_merit,keep'],
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

                if ($errors->has('from_class_id') || $errors->has('to_section_id')) {
                    return;
                }

                // Source class must exist within the caller's branch
                // (unknown/out-of-branch → 422, not a leak).
                $fromClass = SchoolClass::find($this->integer('from_class_id'));

                if ($fromClass === null) {
                    $errors->add('from_class_id', 'The selected class is invalid.');

                    return;
                }

                // The promoted cohort lands in the next class (numeric_level + 1).
                // The target section must belong to that class (branch-scoped) —
                // any other pairing (including the top class, which has no next)
                // is a 422 on to_section_id.
                $nextClass = SchoolClass::query()
                    ->where('numeric_level', $fromClass->numeric_level + 1)
                    ->first();

                $sectionValid = $nextClass !== null && Section::query()
                    ->whereKey($this->integer('to_section_id'))
                    ->where('class_id', $nextClass->id)
                    ->exists();

                if (! $sectionValid) {
                    $errors->add('to_section_id', 'The selected section does not belong to the next class.');
                }
            },
        ];
    }
}
