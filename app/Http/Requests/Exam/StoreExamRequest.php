<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates exam creation. An exam targets either an explicit list of classes
 * (`class_ids`, each existing within the caller's branch) or every class in a
 * branch (`all_classes`). For a given (session, type), no class may already be
 * covered by another exam — the overlap is rejected (422). The exam's branch is
 * derived from the targeted classes (or, for an `all_classes` exam, the
 * caller's / the super-admin-supplied branch) in the service.
 */
class StoreExamRequest extends FormRequest
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
        $rules = [
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'type' => ['required', Rule::enum(ExamType::class)],
            'name' => ['required', 'string', 'max:100'],
            'all_classes' => ['sometimes', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];

        if ($this->boolean('all_classes')) {
            // An all-classes exam has no class list to derive the branch from, so
            // a super admin (who carries no branch) must name one explicitly.
            if ($this->user()?->isSuperAdmin()) {
                $rules['branch_id'] = ['required', 'integer', 'exists:branches,id'];
            }
        } else {
            $rules['class_ids'] = ['required', 'array', 'min:1'];
            $rules['class_ids.*'] = ['integer'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                if ($errors->hasAny(['session_id', 'type', 'class_ids', 'all_classes', 'branch_id'])) {
                    return;
                }

                $all = $this->boolean('all_classes');
                $field = $all ? 'all_classes' : 'class_ids';

                // Resolve the target classes (branch-scoped — out-of-branch ids
                // are model-not-found) and the branch they belong to.
                if ($all) {
                    $branchId = $this->user()?->isSuperAdmin()
                        ? $this->integer('branch_id')
                        : $this->user()?->branch_id;

                    if ($branchId === null) {
                        $errors->add($field, 'A branch is required for an all-classes exam.');

                        return;
                    }

                    $targetIds = SchoolClass::query()->where('branch_id', $branchId)->pluck('id')->all();

                    if ($targetIds === []) {
                        $errors->add($field, 'The selected branch has no classes.');

                        return;
                    }
                } else {
                    /** @var list<int> $ids */
                    $ids = $this->input('class_ids');
                    $classes = SchoolClass::query()->whereIn('id', $ids)->get();

                    if ($classes->count() !== count(array_unique($ids))) {
                        $errors->add('class_ids', 'One or more selected classes are invalid.');

                        return;
                    }

                    if ($classes->pluck('branch_id')->unique()->count() > 1) {
                        $errors->add('class_ids', 'All classes must belong to the same branch.');

                        return;
                    }

                    $targetIds = $classes->pluck('id')->all();
                }

                // No class may already be covered by another exam of this
                // (session, type). `all_classes` exams resolve to their branch's
                // full class set for the overlap test.
                $existing = Exam::query()
                    ->where('session_id', $this->integer('session_id'))
                    ->where('type', $this->input('type'))
                    ->with('classes')
                    ->get();

                foreach ($existing as $exam) {
                    if (array_intersect($targetIds, $exam->classIds()) !== []) {
                        $errors->add($field, 'An exam of this type already exists for one or more of the selected classes in this session.');

                        return;
                    }
                }
            },
        ];
    }
}
