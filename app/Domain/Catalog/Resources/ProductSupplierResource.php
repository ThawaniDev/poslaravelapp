<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'lead_time_days' => $this->lead_time_days,
            'supplier_sku' => $this->supplier_sku,
        ];
    }
}
