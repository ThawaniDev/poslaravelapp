<?php

namespace App\Domain\Inventory\Resources;

use App\Domain\Catalog\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'quantity' => (float) $this->quantity,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'reorder_point' => $this->reorder_point !== null ? (float) $this->reorder_point : null,
            'max_stock_level' => $this->max_stock_level !== null ? (float) $this->max_stock_level : null,
            'average_cost' => $this->average_cost !== null ? (float) $this->average_cost : null,
            'sync_version' => $this->sync_version,

            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
