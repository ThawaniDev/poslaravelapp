<?php

namespace App\Domain\Order\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'transaction_id' => $this->transaction_id,
            'customer_id' => $this->customer_id,
            'order_number' => $this->order_number,
            'source' => $this->source?->value ?? $this->source,
            'status' => $this->status?->value ?? $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'customer_notes' => $this->customer_notes,
            'external_order_id' => $this->external_order_id,
            'delivery_address' => $this->delivery_address,
            'created_by' => $this->created_by,
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('orderStatusHistory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
