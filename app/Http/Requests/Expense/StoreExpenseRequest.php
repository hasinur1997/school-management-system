<?php

namespace App\Http\Requests\Expense;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates manual expense creation. amount must be non-negative; date is any
 * valid date; category_id is optional but, when given, must reference an
 * expense-type category in the caller's branch — an income category is rejected
 * 422. branch_id/created_by are stamped server-side, never accepted from input.
 */
class StoreExpenseRequest extends FormRequest
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
            'item_name' => ['required', 'string', 'max:150'],
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

                // BranchScope auto-applies, so an out-of-branch category is also
                // unresolved here. Only an in-branch expense category is valid.
                $valid = Category::query()
                    ->whereKey($categoryId)
                    ->where('type', CategoryType::Expense)
                    ->exists();

                if (! $valid) {
                    $validator->errors()->add('category_id', 'The selected category is invalid.');
                }
            },
        ];
    }
}
