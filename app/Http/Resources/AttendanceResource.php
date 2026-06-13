<?php

namespace App\Http\Resources;

use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single student attendance record, used by the correction endpoint and the
 * browse listing. Roll/name are included only when the enrollment+student
 * relation is eager loaded (the listing), so single corrections stay cheap.
 *
 * @mixin StudentAttendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'roll_no' => $this->whenLoaded('enrollment', fn () => $this->enrollment->roll_no),
            'name_en' => $this->whenLoaded('enrollment', fn () => $this->enrollment->student->name_en),
            'date' => $this->date->toDateString(),
            'status' => $this->status->value,
            'recorded_by' => $this->recorded_by,
        ];
    }
}
