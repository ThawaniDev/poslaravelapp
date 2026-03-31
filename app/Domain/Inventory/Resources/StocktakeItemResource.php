<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StocktakeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stocktake_id' => $this->stocktake_id,
            'product_id' => $this->product_id,
            'expected_qty' => (float) $this->expected_qty,
            'counted_qty' => $this->counted_qty !== null ? (float) $this->counted_qty : null,
            'variance' => $this->variance !== null ? (float) $this->variance : null,
            'cost_impact' => $this->cost_impact !== null ? (float) $this->cost_impact : null,
            'notes' => $this->notes,
            'counted_at' => $this->counted_at?->toIso8601String(),

            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
        ];
    }
}
