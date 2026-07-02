<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an exam update. Name, dates, status, and the class targeting
 * (`all_classes` / `class_ids`, plus a super-admin `branch_id` for an
 * all-classes exam) are all editable. Session and type stay immutable
 * (prohibited → 422) because they anchor the exam's identity and result
 * weighting. The status may never regress to an earlier lifecycle stage (→ 422),
 * and publishing itself is a separate flow (Task 8.1), so the generic update
 * cannot set status to `published`. When the class targeting changes it must not
 * overlap another exam of the same (session, type) — the same guard as create,
 * excluding the exam being edited.
 */
class UpdateExamRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            // Lifecycle stage may move forward to upcoming/ongoing/completed;
            // `published` is reached only via the dedicated publish flow (8.1).
            'status' => ['sometimes', Rule::enum(ExamStatus::class)->except([ExamStatus::Published])],
            // Class targeting is editable.
            'all_classes' => ['sometimes', 'boolean'],
            // Identity columns stay immutable.
            'session_id' => ['prohibited'],
            'class_id' => ['prohibited'],
            'type' => ['prohibited'],
        ];

        if ($this->boolean('all_classes')) {
            // An all-classes exam has no class list to derive the branch from, so
            // a super admin (who carries no branch) may name one; otherwise the
            // exam keeps its current branch.
            if ($this->user()?->isSuperAdmin()) {
                $rules['branch_id'] = ['sometimes', 'integer', 'exists:branches,id'];
            }
        } elseif ($this->has('class_ids')) {
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
            $this->validateStatusProgression(...),
            $this->validateClassTargeting(...),
        ];
    }

    /**
     * Status only moves forward through the lifecycle.
     */
    private function validateStatusProgression(Validator $validator): void
    {
        /** @var Exam $exam */
        $exam = $this->route('exam');

        if ($validator->errors()->has('status') || ! $this->filled('status')) {
            return;
        }

        $target = ExamStatus::from($this->input('status'));

        if ($target->rank() < $exam->status->rank()) {
            $validator->errors()->add('status', 'Exam status cannot move backwards.');
        }
    }

    /**
     * When the class targeting changes, resolve the target classes (branch-scoped)
     * and reject any overlap with another exam of the same (session, type). The
     * exam's own session/type are immutable, so they anchor the check.
     */
    private function validateClassTargeting(Validator $validator): void
    {
        // Only validate when the request actually changes the targeting.
        if (! $this->has('all_classes') && ! $this->has('class_ids')) {
            return;
        }

        $errors = $validator->errors();

        if ($errors->hasAny(['class_ids', 'all_classes', 'branch_id'])) {
            return;
        }

        /** @var Exam $exam */
        $exam = $this->route('exam');

        $all = $this->boolean('all_classes');
        $field = $all ? 'all_classes' : 'class_ids';

        if ($all) {
            // Super admin may move the exam to a different branch; otherwise it
            // keeps its current branch.
            $branchId = $this->user()?->isSuperAdmin() && $this->has('branch_id')
                ? $this->integer('branch_id')
                : (int) $exam->branch_id;

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

        // No class may already be covered by another exam of this (session, type).
        // The exam being edited is excluded so it never overlaps itself.
        $existing = Exam::query()
            ->where('session_id', $exam->session_id)
            ->where('type', $exam->type)
            ->whereKeyNot($exam->getKey())
            ->with('classes')
            ->get();

        foreach ($existing as $other) {
            if (array_intersect($targetIds, $other->classIds()) !== []) {
                $errors->add($field, 'An exam of this type already exists for one or more of the selected classes in this session.');

                return;
            }
        }
    }
}
