<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'from_store_id' => $this->from_store_id,
            'to_store_id' => $this->to_store_id,
            'status' => $this->status?->value,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'received_by' => $this->received_by,
            'received_at' => $this->received_at?->toIso8601String(),

            'from_store' => $this->whenLoaded('fromStore', fn () => [
                'id' => $this->fromStore->id,
                'name' => $this->fromStore->name,
            ]),
            'to_store' => $this->whenLoaded('toStore', fn () => [
                'id' => $this->toStore->id,
                'name' => $this->toStore->name,
            ]),

            'items' => StockTransferItemResource::collection($this->whenLoaded('stockTransferItems')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
