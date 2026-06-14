<?php

namespace App\Http\Requests\Result;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the (session, class) tuple for annual result generate/publish. The
 * class must exist within the caller's branch (checked through the
 * branch-scoped model so an unknown/out-of-branch tuple reports 422 rather than
 * leak); the session must exist. The three-exams-published and
 * already-published guards live in the service (409s, not validation errors).
 */
class AnnualResultRequest extends FormRequest
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

                // Class must exist within the caller's branch (the unknown-tuple
                // → 422 case).
                if (SchoolClass::find($this->integer('class_id')) === null) {
                    $errors->add('class_id', 'The selected class is invalid.');
                }
            },
        ];
    }
}
