<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'product_id' => $this->product_id,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->expiry_date,

            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
        ];
    }
}
