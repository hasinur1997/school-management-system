<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact student row for index listings: identity plus the current
 * class/section/roll drawn from the active (current-session) enrollment.
 * Full bilingual profile data lives only on StudentResource (show).
 *
 * @mixin Student
 */
class StudentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * class/section/roll resolve from the eager-loaded currentEnrollment so no
     * lazy loading occurs under strict mode; they are null when the student has
     * no enrollment in the current session.
     *
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
