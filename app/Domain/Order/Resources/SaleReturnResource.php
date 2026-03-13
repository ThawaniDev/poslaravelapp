<?php

namespace App\Domain\Order\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'order_id' => $this->order_id,
            'return_number' => $this->return_number,
            'type' => $this->type?->value ?? $this->type,
            'reason_code' => $this->reason_code,
            'refund_method' => $this->refund_method?->value ?? $this->refund_method,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_refund' => (float) $this->total_refund,
            'notes' => $this->notes,
            'processed_by' => $this->processed_by,
        ];
    }
}
