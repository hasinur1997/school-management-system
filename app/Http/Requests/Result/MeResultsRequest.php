<?php

namespace App\Http\Requests\Result;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the self-service result read. Both filters are optional: session_id
 * selects which enrollment to report (current/latest when omitted); student_id
 * names a linked child for parents (ignored for students, who always get their
 * own). The student/parent resolution and linkage check live in the controller.
 */
class MeResultsRequest extends FormRequest
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
            'session_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
        ];
    }
}
