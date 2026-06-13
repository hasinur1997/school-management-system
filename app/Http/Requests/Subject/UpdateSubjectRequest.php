<?php

namespace App\Http\Requests\Subject;

use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSubjectRequest extends FormRequest
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
        /** @var Subject $subject */
        $subject = $this->route('subject');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')
                    ->where('class_id', $subject->class_id)
                    ->ignore($subject),
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

                /** @var Subject $subject */
                $subject = $this->route('subject');

                // Omitted fields keep their current values, so a partial
                // update is checked against what will actually be stored.
                $full = $this->has('full_marks') ? $this->integer('full_marks') : $subject->full_marks;
                $pass = $this->has('pass_marks') ? $this->integer('pass_marks') : $subject->pass_marks;

                if ($pass >= $full) {
                    $validator->errors()->add('pass_marks', 'The pass marks must be less than full marks.');
                }
            },
        ];
    }
}
