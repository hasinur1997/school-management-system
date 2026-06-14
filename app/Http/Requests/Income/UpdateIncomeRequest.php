<?php

namespace App\Http\Requests\Income;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates manual income updates. Same rules as creation; the system-row
 * immutability (403 when payment_id is set) is enforced in the service before
 * any write.
 */
class UpdateIncomeRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'category_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoryId = $this->input('category_id');

                if ($categoryId === null || $validator->errors()->has('category_id')) {
                    return;
                }

                $valid = Category::query()
                    ->whereKey($categoryId)
                    ->where('type', CategoryType::Income)
                    ->exists();

                if (! $valid) {
                    $validator->errors()->add('category_id', 'The selected category is invalid.');
                }
            },
        ];
    }
}
