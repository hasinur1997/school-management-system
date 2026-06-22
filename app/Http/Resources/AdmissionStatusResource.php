<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public status view for an admission application. The lookup is gated on
 * a matching date_of_birth before these application details are exposed.
 *
 * @mixin AdmissionApplication
 */
class AdmissionStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = $this->resource->relationLoaded('branch') ? $this->resource->getRelation('branch') : null;
        $desiredClass = $this->resource->relationLoaded('desiredClass') ? $this->resource->getRelation('desiredClass') : null;
        $currentSession = $this->resource->relationLoaded('currentSession') ? $this->resource->getRelation('currentSession') : null;

        return [
            'application_no' => $this->application_no,
            'branch' => $branch === null ? null : [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'class' => $desiredClass === null ? null : [
                'id' => $desiredClass->id,
                'name' => $desiredClass->name,
            ],
            'session' => $currentSession === null ? null : [
                'id' => $currentSession->id,
                'name' => $currentSession->name,
                'start_date' => $currentSession->start_date?->toDateString(),
                'end_date' => $currentSession->end_date?->toDateString(),
                'is_current' => $currentSession->is_current,
            ],
            'status' => $this->status->value,
            'photo' => $this->photoUrl(),
            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'birth_reg_no' => $this->birth_reg_no,
            'religion' => $this->religion,
            'nationality' => $this->nationality,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
