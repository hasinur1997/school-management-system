<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class SectionResource extends JsonResource
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
            'class_id' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass->public_id),
            'name' => $this->name,
            // Always null until teachers exist (Phase 2) and the assignment
            // endpoint can populate class_teacher_id.
            'class_teacher' => null,
        ];
    }
}
