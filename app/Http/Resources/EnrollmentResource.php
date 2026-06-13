<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single row of a student's class history: the enrollment with its session,
 * class and section names flattened. Session/class/section must be eager loaded
 * by the caller (StudentService::enrollmentHistory) so this never lazy loads.
 *
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
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
            'session' => $this->session?->name,
            'class' => $this->schoolClass?->name,
            'section' => $this->section?->name,
            'roll_no' => $this->roll_no,
            'status' => $this->status->value,
        ];
    }
}
