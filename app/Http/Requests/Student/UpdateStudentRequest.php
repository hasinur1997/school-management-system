<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a student profile update. admission_no and birth_reg_no are
 * immutable identity columns — any attempt to change them is rejected
 * explicitly (422). status moves through PATCH /status, not here.
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

            // Immutable identity columns.
            'admission_no' => ['prohibited'],
            'birth_reg_no' => ['prohibited'],
        ];
    }
}
