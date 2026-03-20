<?php

namespace App\Domain\IndustryRestaurant\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KitchenTicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'order_id'      => $this->order_id,
            'table_id'      => $this->table_id,
            'ticket_number' => $this->ticket_number,
            'items_json'    => $this->items_json,
            'station'       => $this->station,
            'status'        => $this->status?->value,
            'course_number' => $this->course_number,
            'fire_at'       => $this->fire_at?->toIso8601String(),
            'completed_at'  => $this->completed_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
