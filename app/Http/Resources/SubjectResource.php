<?php

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subject
 */
class SubjectResource extends JsonResource
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
            'code' => $this->code,
            'full_marks' => $this->full_marks,
            'pass_marks' => $this->pass_marks,
        ];
    }
}
