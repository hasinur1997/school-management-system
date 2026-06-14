<?php

namespace App\Http\Requests\FeeStructure;

use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates fee-structure creation. The class must exist within the caller's
 * branch (checked through the branch-scoped model so out-of-branch ids report
 * 422 rather than leak); the (branch, session, class) tuple must be unique.
 * The branch_id is derived from the resolved class in the service.
 */
class StoreFeeStructureRequest extends FormRequest
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
            // Money: non-negative, at most 2 decimal places (3dp → 422).
            'monthly_fee' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
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

                // Class must exist within the caller's branch. Skip when
                // session_id or class_id already failed format validation.
                if ($errors->has('class_id') || $errors->has('session_id')) {
                    return;
                }

                if (SchoolClass::find($this->integer('class_id')) === null) {
                    $errors->add('class_id', 'The selected class is invalid.');

                    return;
                }

                $duplicate = FeeStructure::query()
                    ->where('session_id', $this->integer('session_id'))
                    ->where('class_id', $this->integer('class_id'))
                    ->exists();

                if ($duplicate) {
                    $errors->add('class_id', 'Fee already defined for this class and session');
                }
            },
        ];
    }
}
