<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'item_name' => $this->item_name,
            // decimal:2 cast renders money as a 2dp decimal string, e.g. "8200.00".
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'category_id' => $this->category_id,
            'description' => $this->description,
        ];
    }
}
