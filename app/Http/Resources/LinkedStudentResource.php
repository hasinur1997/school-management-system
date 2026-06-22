<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact student shape shared by a parent's linked-students listings
 * (parent payloads and /me/students): identity plus the current
 * class/section drawn from the active (current-session) enrollment. The
 * currentEnrollment + class/section + media must be eager loaded so no lazy
 * loading occurs under strict mode.
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
            'name_en' => $this->name_en,
            'class' => $enrollment?->schoolClass?->name,
            'section' => $enrollment?->section?->name,
            'photo_url' => $this->photoUrl(),
        ];
    }
}
