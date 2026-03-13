<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->expiry_date,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
        ];
    }
}
