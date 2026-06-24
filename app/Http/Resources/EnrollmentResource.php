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
 * The matching `*_id` public ids accompany each name so the client can prefill
 * the academic selects when editing the row (PUT /students/{}/enrollments/{}).
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
            'id' => $this->public_id,
            'session' => $this->session?->name,
            'session_id' => $this->session?->public_id,
            'class' => $this->schoolClass?->name,
            'class_id' => $this->schoolClass?->public_id,
            'section' => $this->section?->name,
            'section_id' => $this->section?->public_id,
            'roll_no' => $this->roll_no,
            'status' => $this->status->value,
        ];
    }
}
