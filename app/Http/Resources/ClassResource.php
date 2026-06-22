<?php

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class ClassResource extends JsonResource
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
            'branch_id' => $this->whenLoaded('branch', fn () => $this->branch->public_id),
            'name' => $this->name,
            'numeric_level' => $this->numeric_level,
            'is_active' => $this->is_active,
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
