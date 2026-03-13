<?php

namespace App\Domain\PosTerminal\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'store_id' => $this->store_id,
            'register_id' => $this->register_id,
            'pos_session_id' => $this->pos_session_id,
            'cashier_id' => $this->cashier_id,
            'customer_id' => $this->customer_id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tip_amount' => (float) $this->tip_amount,
            'total_amount' => (float) $this->total_amount,
            'is_tax_exempt' => (bool) $this->is_tax_exempt,
            'return_transaction_id' => $this->return_transaction_id,
            'notes' => $this->notes,
            'sync_version' => $this->sync_version,
            'items' => TransactionItemResource::collection($this->whenLoaded('transactionItems')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
