<?php

namespace App\Http\Requests\Promotion;

use App\Enums\PromotionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the promotion-history filters: optional session_id, class_id (both
 * filter the source enrollment) and type (bulk|individual). Branch isolation is
 * enforced in the service (the history scopes through the branch-scoped
 * student), so unknown ids simply return no rows rather than leak.
 */
class ListPromotionsRequest extends FormRequest
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
            'session_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::enum(PromotionType::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
