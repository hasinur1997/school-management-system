<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a public admission submission (the only public write endpoint).
 * Strictly hardened: every form field is bounded, the photo/documents are
 * type- and size-capped, branch and class must both exist and be active, and
 * the desired class must belong to the submitted branch. birth_reg_no is a
 * hard, table-wide unique (a rejected applicant cannot re-apply with the same
 * number — see open question #5).
 */
class StoreAdmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Item 1 — applicant name (bilingual)
            'name_bn' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],

            // Items 2–3 — father
            'father_name_bn' => ['required', 'string', 'max:150'],
            'father_name_en' => ['required', 'string', 'max:150'],
            'father_nid' => ['nullable', 'string', 'max:20'],

            // Items 4–5 — mother
            'mother_name_bn' => ['required', 'string', 'max:150'],
            'mother_name_en' => ['required', 'string', 'max:150'],
            'mother_nid' => ['nullable', 'string', 'max:20'],

            // Item 6 — present address + father mobile
            'present_village' => ['required', 'string', 'max:100'],
            'present_post_office' => ['required', 'string', 'max:100'],
            'present_upazila' => ['required', 'string', 'max:100'],
            'present_district' => ['required', 'string', 'max:100'],
            'father_mobile' => ['required', 'string', 'max:20'],

            // Item 7 — permanent address (bn) + mother mobile
            'permanent_village_bn' => ['required', 'string', 'max:100'],
            'permanent_post_office_bn' => ['required', 'string', 'max:100'],
            'permanent_upazila_bn' => ['required', 'string', 'max:100'],
            'permanent_district_bn' => ['required', 'string', 'max:100'],
            'mother_mobile' => ['nullable', 'string', 'max:20'],

            // Item 8 — permanent address (en)
            'permanent_village_en' => ['required', 'string', 'max:100'],
            'permanent_post_office_en' => ['required', 'string', 'max:100'],
            'permanent_upazila_en' => ['required', 'string', 'max:100'],
            'permanent_district_en' => ['required', 'string', 'max:100'],

            // Items 9–11 — identity
            'birth_reg_no' => ['required', 'string', 'max:25', Rule::unique('admission_applications', 'birth_reg_no')],
            'date_of_birth' => ['required', 'date'],
            'religion' => ['required', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'caste' => ['nullable', 'string', 'max:50'],

            // Office-use targets — both must exist and be active; the class
            // must belong to the submitted branch.
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'desired_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->where('branch_id', $this->input('branch_id')),
                ),
            ],

            // Media
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            // Item 13 — previous schooling
            'previous_educations' => ['nullable', 'array'],
            'previous_educations.*.exam_name' => ['required', 'string', 'max:100'],
            'previous_educations.*.institution_name' => ['required', 'string', 'max:150'],
            'previous_educations.*.gpa' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'previous_educations.*.passing_year' => ['nullable', 'integer', 'digits:4'],
            'previous_educations.*.board_roll' => ['nullable', 'string', 'max:30'],
            'previous_educations.*.board_reg_no' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_reg_no.unique' => 'An application with this birth registration number already exists.',
        ];
    }
}
