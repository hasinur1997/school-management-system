<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact student shape shared by a parent's linked-students listings
 * (parent payloads and /me/students): identity plus the current
 * class/section/roll drawn from the active (current-session) enrollment. Mirrors
 * StudentListResource so the parent "Students" table can render the same columns
 * (admission no, roll, status) as the admin students list. The currentEnrollment
 * + class/section + media must be eager loaded so no lazy loading occurs under
 * strict mode.
 *
 * @mixin Student
 */
class LinkedStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->currentEnrollment;

        return [
            'id' => $this->public_id,
            'admission_no' => $this->admission_no,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'class' => $enrollment?->schoolClass?->name,
            'section' => $enrollment?->section?->name,
            'roll_no' => $enrollment?->roll_no,
            'status' => $this->status->value,
            'photo_url' => $this->photoUrl(),
        ];
    }
}
