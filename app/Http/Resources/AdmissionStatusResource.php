<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public status view: status plus the rejection reason (null unless
 * rejected). Nothing else is exposed — the lookup is gated on a matching
 * date_of_birth.
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
        return [
            'application_no' => $this->application_no,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
