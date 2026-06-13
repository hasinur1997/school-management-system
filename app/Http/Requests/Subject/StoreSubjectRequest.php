<?php

namespace App\Http\Requests\Subject;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubjectRequest extends FormRequest
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
        /** @var SchoolClass $class */
        $class = $this->route('class');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')->where('class_id', $class->id),
            ],
            'code' => ['nullable', 'string', 'max:20'],
            'full_marks' => ['sometimes', 'integer', 'between:1,32767'],
            'pass_marks' => ['sometimes', 'integer', 'between:0,32767'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['full_marks', 'pass_marks'])) {
                    return;
                }

                // Omitted fields fall back to the column defaults.
                if ($this->integer('pass_marks', 33) >= $this->integer('full_marks', 100)) {
                    $validator->errors()->add('pass_marks', 'The pass marks must be less than full marks.');
                }
            },
        ];
    }
}
