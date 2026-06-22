<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The student summary returned by the admission approval endpoint: identity
 * plus the freshly created enrollment (session/class/section names + roll).
 * The enrollment is set on the model by the service with its session, class
 * and section relations preloaded, so nothing lazy loads here.
 *
 * @mixin Student
 */
class ApprovedStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->enrollments->first();

        return [
            'id' => $this->public_id,
            'admission_no' => $this->admission_no,
            'name_en' => $this->name_en,
            'enrollment' => [
                'session' => $enrollment->session->name,
                'class' => $enrollment->schoolClass->name,
                'section' => $enrollment->section->name,
                'roll_no' => $enrollment->roll_no,
            ],
        ];
    }
}
