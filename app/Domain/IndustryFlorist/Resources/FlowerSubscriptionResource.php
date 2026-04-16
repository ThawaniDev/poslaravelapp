<?php

namespace App\Domain\IndustryFlorist\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FlowerSubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'store_id'                => $this->store_id,
            'customer_id'             => $this->customer_id,
            'arrangement_template_id' => $this->arrangement_template_id,
            'frequency'               => $this->frequency?->value,
            'delivery_day'            => $this->delivery_day,
            'delivery_address'        => $this->delivery_address,
            'price_per_delivery'      => $this->price_per_delivery,
            'is_active'               => $this->is_active,
            'next_delivery_date'      => $this->next_delivery_date?->toDateString(),
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}
