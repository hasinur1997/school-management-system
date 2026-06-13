<?php

namespace App\Http\Requests\Parent;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates linking a single student to a parent. The student must exist in
 * the caller's branch; the branch-scoped lookup makes a foreign-branch id
 * report 422 rather than leaking the record's existence.
 */
class LinkStudentRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = $this->integer('student_id');

            if ($id > 0 && ! Student::whereKey($id)->exists()) {
                $validator->errors()->add('student_id', 'The selected student is invalid or outside your branch.');
            }
        });
    }
}
