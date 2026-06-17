<?php

namespace App\Http\Requests\IdCard;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a batch ID card request. class_id and session_id are required;
 * section_id is optional. The class/section must exist within the caller's
 * branch (checked through the branch-scoped models, so an unknown/out-of-branch
 * id reports 422 rather than leaking existence). The empty-cohort guard lives in
 * the service (422 "No eligible students"), not here.
 */
class BatchIdCardRequest extends FormRequest
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
            'class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
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

                if (SchoolClass::find($this->integer('class_id')) === null) {
                    $errors->add('class_id', 'The selected class is invalid.');

                    return;
                }

                // A given section must belong to the (in-branch) class.
                if ($this->filled('section_id') && Section::query()
                    ->whereKey($this->integer('section_id'))
                    ->where('class_id', $this->integer('class_id'))
                    ->doesntExist()
                ) {
                    $errors->add('section_id', 'The selected section is invalid.');
                }
            },
        ];
    }
}
