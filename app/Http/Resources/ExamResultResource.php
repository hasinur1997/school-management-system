<?php

namespace App\Http\Resources;

use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of an exam's tabular results. total_marks and gpa are decimal-cast,
 * so they serialize as fixed-precision strings ("428.50", "4.25").
 *
 * @mixin ExamResult
 */
class ExamResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enrollment_id' => $this->enrollment_id,
            'roll_no' => $this->whenLoaded('enrollment', fn () => $this->enrollment->roll_no),
            'name_en' => $this->whenLoaded('enrollment', fn () => $this->enrollment->student->name_en),
            'total_marks' => $this->total_marks,
            'gpa' => $this->gpa,
            'grade' => $this->grade,
            'is_passed' => $this->is_passed,
        ];
    }
}
