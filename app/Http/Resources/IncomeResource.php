<?php

namespace App\Http\Resources;

use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Income
 */
class IncomeResource extends JsonResource
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
            'title' => $this->title,
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "25000.00".
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'category_id' => $this->whenLoaded('category', fn () => $this->category?->public_id),
            'description' => $this->description,
            // payment_id set → system-generated fee income, immutable.
            'is_system' => $this->isSystem(),
        ];
    }
}
