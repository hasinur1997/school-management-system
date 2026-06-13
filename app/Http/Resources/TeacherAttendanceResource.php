<?php

namespace App\Http\Resources;

use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A teacher's daily attendance record, returned by the check-in/check-out
 * endpoints.
 *
 * @mixin TeacherAttendance
 */
class TeacherAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'status' => $this->status->value,
        ];
    }
}
