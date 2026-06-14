<?php

namespace App\Http\Resources;

use App\Models\Mark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mark
 */
class MarkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'roll_no' => $this->whenLoaded('enrollment', fn () => $this->enrollment->roll_no),
            'name_en' => $this->whenLoaded('enrollment', fn () => $this->enrollment->student->name_en),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject->name),
            'obtained_marks' => (float) $this->obtained_marks,
            'grade' => $this->grade,
            'grade_point' => $this->grade_point,
        ];
    }
}
