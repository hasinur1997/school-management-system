<?php

namespace App\Http\Requests\Parent;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates parent creation. Phone and email must be free across the login
 * (users) table — they become the parent's credentials — so a clash reports
 * 422 before the create transaction runs. Every student_id must resolve to a
 * student in the caller's branch; the branch-scoped lookup in withValidator()
 * makes a foreign-branch id indistinguishable from a non-existent one (422),
 * never leaking another branch's records.
 */
class StoreParentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
        ];
    }

    /**
     * Ensure every submitted student_id exists within the caller's branch.
     * Student carries BranchScope, so the count comparison silently drops
     * out-of-branch ids and reports them as invalid.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = collect($this->input('student_ids', []))
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique();

            if ($ids->isEmpty()) {
                return;
            }

            $found = Student::whereIn('id', $ids)->pluck('id');

            if ($ids->diff($found)->isNotEmpty()) {
                $validator->errors()->add('student_ids', 'One or more students are invalid or outside your branch.');
            }
        });
    }
}
