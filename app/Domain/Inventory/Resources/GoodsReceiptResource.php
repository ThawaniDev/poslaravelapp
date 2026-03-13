<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status?->value,
            'reference_number' => $this->reference_number,
            'total_cost' => $this->total_cost !== null ? (float) $this->total_cost : null,
            'notes' => $this->notes,
            'received_by' => $this->received_by,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),

            'items' => GoodsReceiptItemResource::collection($this->whenLoaded('goodsReceiptItems')),
            'batches' => StockBatchResource::collection($this->whenLoaded('stockBatches')),

            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}
