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
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status->value,
        ];
    }
}
