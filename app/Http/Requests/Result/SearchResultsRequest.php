<?php

namespace App\Http\Requests\Result;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a result search: exactly one of the two query styles must be
 * supplied — an admission_no, OR the full (session, class, section, roll)
 * coordinates. Mixing the two or supplying neither (an incomplete coordinate
 * set counts as neither) is a 422. Branch isolation rides on the resolution in
 * ResultService (both styles go through the branch-scoped Student).
 */
class SearchResultsRequest extends FormRequest
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
            'admission_no' => ['nullable', 'string'],
            'session_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'roll_no' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasAdmission = $this->filled('admission_no');

                $coordKeys = ['session_id', 'class_id', 'section_id', 'roll_no'];
                $filledCoords = array_filter($coordKeys, fn (string $key): bool => $this->filled($key));
                $hasAnyCoord = $filledCoords !== [];
                $hasAllCoords = count($filledCoords) === count($coordKeys);

                if ($hasAdmission && $hasAnyCoord) {
                    $validator->errors()->add('admission_no', 'Provide either admission_no or the class coordinates, not both.');
                } elseif (! $hasAdmission && ! $hasAllCoords) {
                    $validator->errors()->add('admission_no', 'Provide admission_no, or all of session_id, class_id, section_id and roll_no.');
                }
            },
        ];
    }

    /**
     * The resolved search criteria: the admission_no style when present,
     * otherwise the coordinate style. Only reached once validation passes, so
     * exactly one style is complete.
     *
     * @return array{admission_no?: string, session_id?: int, class_id?: int, section_id?: int, roll_no?: int}
     */
    public function criteria(): array
    {
        if ($this->filled('admission_no')) {
            return ['admission_no' => $this->string('admission_no')->toString()];
        }

        return [
            'session_id' => $this->integer('session_id'),
            'class_id' => $this->integer('class_id'),
            'section_id' => $this->integer('section_id'),
            'roll_no' => $this->integer('roll_no'),
        ];
    }
}
