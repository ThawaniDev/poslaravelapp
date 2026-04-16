<?php

namespace App\Domain\IndustryFlorist\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FlowerFreshnessLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'product_id'              => $this->product_id,
            'store_id'                => $this->store_id,
            'received_date'           => $this->received_date?->toDateString(),
            'expected_vase_life_days' => $this->expected_vase_life_days,
            'markdown_date'           => $this->markdown_date?->toDateString(),
            'dispose_date'            => $this->dispose_date?->toDateString(),
            'quantity'                => $this->quantity,
            'status'                  => $this->status?->value,
        ];
    }
}
