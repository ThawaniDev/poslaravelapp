<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'store_id' => $this->store_id,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status?->value,
            'reference_number' => $this->reference_number,
            'total_cost' => $this->total_cost !== null ? (float) $this->total_cost : null,
            'expected_date' => $this->expected_date,
            'notes' => $this->notes,
            'created_by' => $this->created_by,

            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),

            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('purchaseOrderItems')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
