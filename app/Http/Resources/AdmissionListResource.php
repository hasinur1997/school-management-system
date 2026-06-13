<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A compact admission row for the review list: identity, desired class, father
 * mobile, status, and the submission timestamp (created_at).
 *
 * @mixin AdmissionApplication
 */
class AdmissionListResource extends JsonResource
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
            'name_en' => $this->name_en,
            'desired_class' => $this->desiredClass === null ? null : [
                'id' => $this->desiredClass->id,
                'name' => $this->desiredClass->name,
            ],
            'father_mobile' => $this->father_mobile,
            'status' => $this->status->value,
            'submitted_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
