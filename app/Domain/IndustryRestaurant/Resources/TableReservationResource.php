<?php

namespace App\Domain\IndustryRestaurant\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TableReservationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'table_id'         => $this->table_id,
            'customer_name'    => $this->customer_name,
            'customer_phone'   => $this->customer_phone,
            'party_size'       => $this->party_size,
            'reservation_date' => $this->reservation_date?->toDateString(),
            'reservation_time' => $this->reservation_time,
            'duration_minutes' => $this->duration_minutes,
            'status'           => $this->status?->value,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
