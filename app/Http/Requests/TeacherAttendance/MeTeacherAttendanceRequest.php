<?php

namespace App\Http\Requests\TeacherAttendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the teacher's own monthly history query. Both month and year are
 * optional and default to the current month/year; an out-of-range value (or
 * non-integer) is a 422 validation error.
 */
class MeTeacherAttendanceRequest extends FormRequest
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
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * The requested month, defaulting to the current calendar month.
     */
    public function month(): int
    {
        return $this->integer('month', (int) now()->month);
    }

    /**
     * The requested year, defaulting to the current calendar year.
     */
    public function year(): int
    {
        return $this->integer('year', (int) now()->year);
    }
}
