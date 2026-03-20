<?php

namespace App\Domain\IndustryRestaurant\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantTableResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'table_number'     => $this->table_number,
            'display_name'     => $this->display_name,
            'seats'            => $this->seats,
            'zone'             => $this->zone,
            'position_x'       => $this->position_x,
            'position_y'       => $this->position_y,
            'status'           => $this->status?->value,
            'current_order_id' => $this->current_order_id,
            'is_active'        => $this->is_active,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
