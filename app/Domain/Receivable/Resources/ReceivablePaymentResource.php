<?php

namespace App\Domain\Receivable\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivablePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receivable_id' => $this->receivable_id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'payment_method' => $this->payment_method,
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
            'settled_by' => $this->settled_by,
            'settled_by_name' => $this->whenLoaded('settledBy', fn () => $this->settledBy?->name),
            'settled_at' => $this->settled_at?->toISOString(),
        ];
    }
}
