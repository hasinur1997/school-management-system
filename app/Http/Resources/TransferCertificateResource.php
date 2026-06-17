<?php

namespace App\Http\Resources;

use App\Models\TransferCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A transfer certificate for issue/list/show responses: identity, the embedded
 * student summary, the issuing details, and the URL of the stored PDF.
 *
 * @mixin TransferCertificate
 */
class TransferCertificateResource extends JsonResource
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
            'tc_no' => $this->tc_no,
            'student' => StudentListResource::make($this->whenLoaded('student')),
            'reason' => $this->reason,
            'issue_date' => $this->issue_date?->toDateString(),
            'pdf_url' => route('v1.tcs.pdf', $this->resource, absolute: false),
        ];
    }
}
