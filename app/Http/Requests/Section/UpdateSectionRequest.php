<?php

namespace App\Http\Requests\Section;

use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
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
        /** @var Section $section */
        $section = $this->route('section');

        return [
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('sections', 'name')
                    ->where('class_id', $section->class_id)
                    ->ignore($section),
            ],
        ];
    }
}
