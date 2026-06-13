<?php

namespace App\Http\Requests\SchoolClass;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'numeric_level' => [
                'required',
                'integer',
                'between:1,12',
                Rule::unique('school_classes', 'numeric_level')
                    ->where('branch_id', $class->branch_id)
                    ->ignore($class),
            ],
            'is_active' => ['sometimes', 'boolean'],
            // A class never changes branch; submitted values are ignored.
            'branch_id' => ['exclude'],
        ];
    }
}
