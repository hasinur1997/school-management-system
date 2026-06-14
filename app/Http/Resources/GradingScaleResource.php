<?php

namespace App\Http\Resources;

use App\Models\GradingScale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GradingScale
 */
class GradingScaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grade' => $this->grade,
            'min_marks' => $this->min_marks,
            'max_marks' => $this->max_marks,
            'grade_point' => $this->grade_point,
            'is_fail' => $this->is_fail,
        ];
    }
}
