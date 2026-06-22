<?php

namespace App\Http\Resources;

use App\Models\CheckinIpWhitelist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckinIpWhitelist
 */
class CheckinIpResource extends JsonResource
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
            'branch_id' => $this->whenLoaded('branch', fn () => $this->branch->public_id),
            'ip_address' => $this->ip_address,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
