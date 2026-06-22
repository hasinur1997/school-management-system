<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full student profile for the show endpoint: every schema field
 * plus a class-history enrollments summary and the photo URL. The enrollments
 * key is only present when eager loaded.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'application_id' => $this->application_id,
            'admission_no' => $this->admission_no,

            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en,

            'father_name_bn' => $this->father_name_bn,
            'father_name_en' => $this->father_name_en,
            'father_nid' => $this->father_nid,

            'mother_name_bn' => $this->mother_name_bn,
            'mother_name_en' => $this->mother_name_en,
            'mother_nid' => $this->mother_nid,

            'present_village' => $this->present_village,
            'present_post_office' => $this->present_post_office,
            'present_upazila' => $this->present_upazila,
            'present_district' => $this->present_district,
            'present_division' => $this->present_division,

            'permanent_village' => $this->permanent_village,
            'permanent_post_office' => $this->permanent_post_office,
            'permanent_upazila' => $this->permanent_upazila,
            'permanent_district' => $this->permanent_district,
            'permanent_division' => $this->permanent_division,

            'father_mobile' => $this->father_mobile,
            'mother_mobile' => $this->mother_mobile,

            'birth_reg_no' => $this->birth_reg_no,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'religion' => $this->religion,
            'nationality' => $this->nationality,
            'caste' => $this->caste,

            'status' => $this->status->value,
            'admitted_at' => $this->admitted_at?->toDateString(),
            'photo_url' => $this->photoUrl(),

            'enrollments' => $this->whenLoaded('enrollments', fn () => $this->enrollments->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'session' => $enrollment->session === null ? null : [
                    'id' => $enrollment->session->id,
                    'name' => $enrollment->session->name,
                ],
                'class' => $enrollment->schoolClass === null ? null : [
                    'id' => $enrollment->schoolClass->id,
                    'name' => $enrollment->schoolClass->name,
                ],
                'section' => $enrollment->section === null ? null : [
                    'id' => $enrollment->section->id,
                    'name' => $enrollment->section->name,
                ],
                'roll_no' => $enrollment->roll_no,
                'status' => $enrollment->status->value,
            ])->all()),
        ];
    }
}
