<?php

namespace App\Domain\IndustryBakery\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomCakeOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'customer_id'         => $this->customer_id,
            'order_id'            => $this->order_id,
            'description'         => $this->description,
            'size'                => $this->size,
            'flavor'              => $this->flavor,
            'decoration_notes'    => $this->decoration_notes,
            'delivery_date'       => $this->delivery_date,
            'delivery_time'       => $this->delivery_time,
            'price'               => $this->price ? (float) $this->price : null,
            'deposit_paid'        => $this->deposit_paid ? (float) $this->deposit_paid : null,
            'status'              => $this->status,
            'reference_image_url' => $this->reference_image_url,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
