<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of promotion history: the student, the class they moved out of and
 * into (the to-class is null for a held-back student), the type (bulk|
 * individual) and when it happened. Student + both enrollments' classes must be
 * eager loaded so no lazy loading occurs.
 *
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'student' => [
                'id' => $this->student->public_id,
                'name_en' => $this->student->name_en,
            ],
            'from' => [
                'class' => $this->fromEnrollment?->schoolClass?->name,
            ],
            'to' => [
                'class' => $this->toEnrollment?->schoolClass?->name,
            ],
            'type' => $this->type->value,
            'promoted_at' => $this->promoted_at?->toIso8601String(),
        ];
    }
}
