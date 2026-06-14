<?php

namespace App\Http\Requests\Promotion;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the (session, class) tuple for the promotion preview. Both params
 * are required (missing → 422). The class must exist within the caller's branch
 * (checked through the branch-scoped model so an unknown/out-of-branch class
 * reports 422 rather than leak); the session must exist. The
 * annual-results-published guard lives in the service (409, not a validation
 * error).
 */
class PreviewPromotionRequest extends FormRequest
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

                if ($errors->has('class_id') || $errors->has('session_id')) {
                    return;
                }

                // Class must exist within the caller's branch (the
                // unknown/out-of-branch class → 422 case).
                if (SchoolClass::find($this->integer('class_id')) === null) {
                    $errors->add('class_id', 'The selected class is invalid.');
                }
            },
        ];
    }
}
