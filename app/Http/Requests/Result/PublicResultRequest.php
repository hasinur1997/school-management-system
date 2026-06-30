<?php

namespace App\Http\Requests\Result;

use App\Enums\ExamType;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the public result lookup. Branch/class ids may arrive as public
 * ids and are resolved by ResolvePublicIds before validation.
 */
class PublicResultRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('semester') && $this->has('semister')) {
            $this->merge(['semester' => $this->query('semister')]);
        }
    }

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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'roll_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where('is_active', true)],
            'year' => ['required', 'integer', 'between:1900,2100'],
            'semester' => ['required', Rule::enum(ExamType::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['branch_id', 'class_id'])) {
                    return;
                }

                $classBelongsToBranch = SchoolClass::query()
                    ->whereKey($this->integer('class_id'))
                    ->where('branch_id', $this->integer('branch_id'))
                    ->exists();

                if (! $classBelongsToBranch) {
                    $validator->errors()->add('class_id', 'The selected class does not belong to the selected branch.');
                }
            },
        ];
    }

    /**
     * @return array{branch_id: int, roll_no: int, class_id: int, year: int, semester: ExamType}
     */
    public function criteria(): array
    {
        return [
            'branch_id' => $this->integer('branch_id'),
            'roll_no' => $this->integer('roll_no'),
            'class_id' => $this->integer('class_id'),
            'year' => $this->integer('year'),
            'semester' => ExamType::from($this->string('semester')->toString()),
        ];
    }
}
