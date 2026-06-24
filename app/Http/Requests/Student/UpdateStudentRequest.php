<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a student profile update. admission_no is an immutable identity
 * column — any attempt to change it is rejected explicitly (422). birth_reg_no
 * is editable but stays unique across students (the current student is ignored);
 * it's `sometimes` so a payload that omits it leaves it unchanged. status moves
 * through PATCH /status, not here.
 */
class UpdateStudentRequest extends FormRequest
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
            'name_bn' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],

            'father_name_bn' => ['required', 'string', 'max:150'],
            'father_name_en' => ['required', 'string', 'max:150'],
            'father_nid' => ['nullable', 'string', 'max:20'],

            'mother_name_bn' => ['required', 'string', 'max:150'],
            'mother_name_en' => ['required', 'string', 'max:150'],
            'mother_nid' => ['nullable', 'string', 'max:20'],

            'present_village' => ['required', 'string', 'max:100'],
            'present_post_office' => ['required', 'string', 'max:100'],
            'present_upazila' => ['required', 'string', 'max:100'],
            'present_district' => ['required', 'string', 'max:100'],
            'present_division' => ['required', 'string', 'max:100'],

            'permanent_village' => ['required', 'string', 'max:100'],
            'permanent_post_office' => ['required', 'string', 'max:100'],
            'permanent_upazila' => ['required', 'string', 'max:100'],
            'permanent_district' => ['required', 'string', 'max:100'],
            'permanent_division' => ['required', 'string', 'max:100'],

            'father_mobile' => ['required', 'string', 'max:20'],
            'mother_mobile' => ['nullable', 'string', 'max:20'],

            'date_of_birth' => ['required', 'date'],
            'religion' => ['required', 'string', 'max:50'],
            'nationality' => ['required', 'string', 'max:50'],
            'caste' => ['nullable', 'string', 'max:50'],

            // Editable but unique across students (ignore the current record).
            'birth_reg_no' => [
                'sometimes', 'required', 'string', 'max:25',
                Rule::unique('students', 'birth_reg_no')->ignore($this->route('student')),
            ],

            // Immutable identity column.
            'admission_no' => ['prohibited'],
        ];
    }
}
