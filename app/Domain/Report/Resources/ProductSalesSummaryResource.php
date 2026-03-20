<?php

namespace App\Domain\Report\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSalesSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'product_id'      => $this->product_id,
            'date'            => $this->date?->toDateString(),
            'quantity_sold'   => $this->quantity_sold,
            'revenue'         => $this->revenue,
            'cost'            => $this->cost,
            'discount_amount' => $this->discount_amount,
            'tax_amount'      => $this->tax_amount,
            'return_quantity' => $this->return_quantity,
            'return_amount'   => $this->return_amount,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
