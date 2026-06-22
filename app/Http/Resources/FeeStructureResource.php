<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeStructure
 */
class FeeStructureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'session_id' => $this->whenLoaded('session', fn () => $this->session->public_id),
            'class_id' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass->public_id),
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "1500.00".
            'monthly_fee' => $this->monthly_fee,
        ];
    }
}
