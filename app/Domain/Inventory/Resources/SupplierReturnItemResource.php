<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_return_id' => $this->supplier_return_id,
            'product_id' => $this->product_id,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'reason' => $this->reason,
            'batch_number' => $this->batch_number,

            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku ?? null,
            ]),
        ];
    }
}
