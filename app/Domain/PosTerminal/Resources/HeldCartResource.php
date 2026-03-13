<?php

namespace App\Domain\PosTerminal\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HeldCartResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'register_id' => $this->register_id,
            'cashier_id' => $this->cashier_id,
            'customer_id' => $this->customer_id,
            'cart_data' => $this->cart_data,
            'label' => $this->label,
            'held_at' => $this->held_at?->toISOString(),
            'recalled_at' => $this->recalled_at?->toISOString(),
            'recalled_by' => $this->recalled_by,
        ];
    }
}
