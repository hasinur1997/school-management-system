<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The receipt returned right after a public submission: the generated
 * application number and the (always pending) status.
 *
 * @mixin AdmissionApplication
 */
class AdmissionSubmissionResource extends JsonResource
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
        ];
    }
}
