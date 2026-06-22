<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
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
            'name' => $this->name,
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "45000.00".
            'value' => $this->value,
            'description' => $this->description,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'status' => $this->status->value,
        ];
    }
}
