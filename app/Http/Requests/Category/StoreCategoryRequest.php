<?php

namespace App\Http\Requests\Category;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates category creation. The (branch, name, type) tuple must be unique
 * within the caller's branch; an invalid type is rejected. branch_id is
 * stamped automatically by BelongsToBranch.
 */
class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(CategoryType::class)],
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

                // Skip the duplicate check when name/type already failed format
                // validation.
                if ($errors->has('name') || $errors->has('type')) {
                    return;
                }

                $duplicate = Category::query()
                    ->where('name', $this->string('name'))
                    ->where('type', $this->string('type'))
                    ->exists();

                if ($duplicate) {
                    $errors->add('name', 'Category already exists');
                }
            },
        ];
    }
}
