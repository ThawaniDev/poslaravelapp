<?php

namespace App\Domain\ThawaniIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThawaniOrderMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'store_id'             => $this->store_id,
            'order_id'             => $this->order_id,
            'thawani_order_id'     => $this->thawani_order_id,
            'thawani_order_number' => $this->thawani_order_number,
            'status'               => $this->status,
            'delivery_type'        => $this->delivery_type,
            'customer_name'        => $this->customer_name,
            'customer_phone'       => $this->customer_phone,
            'delivery_address'     => $this->delivery_address,
            'order_total'          => (float) $this->order_total,
            'commission_amount'    => $this->commission_amount ? (float) $this->commission_amount : null,
            'rejection_reason'     => $this->rejection_reason,
            'accepted_at'          => $this->accepted_at?->toIso8601String(),
            'prepared_at'          => $this->prepared_at?->toIso8601String(),
            'completed_at'         => $this->completed_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
