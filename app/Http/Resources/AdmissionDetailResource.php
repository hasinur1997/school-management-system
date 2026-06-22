<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use App\Models\AdmissionPreviousEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The full admission application detail: every form field, the applicant photo
 * and supporting-document URLs, the previous-education child rows, and the
 * review audit pointers. Media and relations must be eager loaded by the
 * service so nothing lazy loads here.
 *
 * @mixin AdmissionApplication
 */
class AdmissionDetailResource extends JsonResource
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
            'application_no' => $this->application_no,
            'desired_class' => $this->desiredClass === null ? null : [
                'id' => $this->desiredClass->id,
                'name' => $this->desiredClass->name,
            ],

            // Item 1 — applicant name (bilingual)
            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en,

            // Items 2–5 — parents
            'father_name_bn' => $this->father_name_bn,
            'father_name_en' => $this->father_name_en,
            'father_nid' => $this->father_nid,
            'mother_name_bn' => $this->mother_name_bn,
            'mother_name_en' => $this->mother_name_en,
            'mother_nid' => $this->mother_nid,

            // Item 6 — present address + father mobile
            'present_village' => $this->present_village,
            'present_post_office' => $this->present_post_office,
            'present_upazila' => $this->present_upazila,
            'present_district' => $this->present_district,
            'present_division' => $this->present_division,
            'father_mobile' => $this->father_mobile,

            // Item 7 — permanent address + mother mobile
            'permanent_village' => $this->permanent_village,
            'permanent_post_office' => $this->permanent_post_office,
            'permanent_upazila' => $this->permanent_upazila,
            'permanent_district' => $this->permanent_district,
            'permanent_division' => $this->permanent_division,
            'mother_mobile' => $this->mother_mobile,

            // Items 9–11 — identity
            'birth_reg_no' => $this->birth_reg_no,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'religion' => $this->religion,
            'nationality' => $this->nationality,
            'caste' => $this->caste,

            // Media
            'photo_url' => $this->photoUrl(),
            'documents' => $this->getMedia('documents')
                ->map(fn (Media $media): array => [
                    'name' => $media->file_name,
                    'url' => $media->getUrl(),
                ])->all(),

            // Item 13 — previous schooling
            'previous_educations' => $this->previousEducations
                ->map(fn (AdmissionPreviousEducation $education): array => [
                    'id' => $education->id,
                    'exam_name' => $education->exam_name,
                    'institution_name' => $education->institution_name,
                    'gpa' => $education->gpa,
                    'passing_year' => $education->passing_year,
                    'board_roll' => $education->board_roll,
                    'board_reg_no' => $education->board_reg_no,
                ])->all(),

            // Review lifecycle
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ],
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitted_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
