<?php

namespace App\Http\Requests\Section;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
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
                'max:30',
                Rule::unique('sections', 'name')->where('class_id', $class->id),
            ],
        ];
    }
}
