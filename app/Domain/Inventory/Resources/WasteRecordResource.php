<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WasteRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'quantity' => (float) $this->quantity,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'total_cost' => $this->unit_cost !== null ? (float) ($this->quantity * $this->unit_cost) : null,
            'reason' => $this->reason?->value,
            'batch_number' => $this->batch_number,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,

            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
