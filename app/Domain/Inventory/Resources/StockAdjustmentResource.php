<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'type' => $this->type?->value,
            'reason_code' => $this->reason_code,
            'notes' => $this->notes,
            'adjusted_by' => $this->adjusted_by,

            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('stockAdjustmentItems')),
        ];
    }
}
