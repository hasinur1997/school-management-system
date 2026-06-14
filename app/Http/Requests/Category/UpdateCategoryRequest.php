<?php

namespace App\Http\Requests\Category;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a category update. name and type are editable; the resulting
 * (branch, name, type) tuple must stay unique (excluding this category).
 */
class UpdateCategoryRequest extends FormRequest
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

                if ($errors->has('name') || $errors->has('type')) {
                    return;
                }

                /** @var Category $category */
                $category = $this->route('category');

                $duplicate = Category::query()
                    ->whereKeyNot($category->getKey())
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
