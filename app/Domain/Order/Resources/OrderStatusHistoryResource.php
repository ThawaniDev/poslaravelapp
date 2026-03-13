<?php

namespace App\Domain\Order\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'from_status' => $this->from_status?->value ?? $this->from_status,
            'to_status' => $this->to_status?->value ?? $this->to_status,
            'changed_by' => $this->changed_by,
            'notes' => $this->notes,
        ];
    }
}
