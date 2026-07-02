<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'session_id' => $this->whenLoaded('session', fn () => $this->session->public_id),
            // Branch the exam belongs to — feeds the super-admin edit form so it
            // can pre-select the current branch.
            'branch_id' => $this->whenLoaded('branch', fn () => $this->branch->public_id),
            'type' => $this->type->value,
            'name' => $this->name,
            // An exam targets all classes (the flag) or an explicit list (the
            // pivot). `class_ids` feeds the edit form; `classes` carries names
            // for display. Both come from the loaded `classes` relation, so they
            // are empty for an `all_classes` exam.
            'all_classes' => (bool) $this->all_classes,
            'class_ids' => $this->whenLoaded(
                'classes',
                fn () => $this->classes->pluck('public_id')->all(),
            ),
            'classes' => $this->whenLoaded(
                'classes',
                fn () => $this->classes
                    ->map(fn (SchoolClass $class) => [
                        'id' => $class->public_id,
                        'name' => $class->name,
                    ])
                    ->all(),
            ),
            // The concrete set of classes this exam actually covers — the
            // explicit pivot, or every class in the branch when `all_classes`
            // is set. Unlike `classes` (which is empty for an all-classes exam),
            // this is never empty, so the marks-entry class picker can scope
            // itself to valid classes instead of every branch's classes.
            'effective_classes' => $this->effectiveClasses()
                ->map(fn (SchoolClass $class) => [
                    'id' => $class->public_id,
                    'name' => $class->name,
                ])
                ->values()
                ->all(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status->value,
        ];
    }
}
