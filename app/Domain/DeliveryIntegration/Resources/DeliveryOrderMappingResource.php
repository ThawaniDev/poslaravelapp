<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'order_id'             => $this->order_id,
            'store_id'             => $this->store_id,
            'platform'             => $this->platform,
            'external_order_id'    => $this->external_order_id,
            'external_status'      => $this->external_status,
            'delivery_status'      => $this->delivery_status ?? $this->external_status,
            'customer_name'        => $this->customer_name,
            'customer_phone'       => $this->customer_phone,
            'delivery_address'     => $this->delivery_address,
            'delivery_fee'         => $this->delivery_fee ? (float) $this->delivery_fee : null,
            'subtotal'             => $this->subtotal ? (float) $this->subtotal : null,
            'total_amount'         => $this->total_amount ? (float) $this->total_amount : null,
            'items_count'          => $this->items_count ? (int) $this->items_count : null,
            'commission_amount'    => $this->commission_amount ? (float) $this->commission_amount : null,
            'commission_percent'   => $this->commission_percent ? (float) $this->commission_percent : null,
            'rejection_reason'     => $this->rejection_reason,
            'notes'                => $this->notes,
            'estimated_prep_minutes' => $this->estimated_prep_minutes ? (int) $this->estimated_prep_minutes : null,
            'accepted_at'          => $this->accepted_at?->toIso8601String(),
            'ready_at'             => $this->ready_at?->toIso8601String(),
            'dispatched_at'        => $this->dispatched_at?->toIso8601String(),
            'delivered_at'         => $this->delivered_at?->toIso8601String(),
            'raw_payload'          => $this->raw_payload,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
