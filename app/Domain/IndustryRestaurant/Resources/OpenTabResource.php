<?php

namespace App\Domain\IndustryRestaurant\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OpenTabResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'order_id'      => $this->order_id,
            'customer_name' => $this->customer_name,
            'table_id'      => $this->table_id,
            'opened_at'     => $this->opened_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'status'        => $this->status?->value,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
