<?php

namespace App\Domain\PosTerminal\Resources;

use App\Domain\Payment\Resources\PaymentResource;
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
            'zatca_uuid' => $this->zatca_uuid,
            'zatca_hash' => $this->zatca_hash,
            'zatca_qr_code' => $this->zatca_qr_code,
            'zatca_status' => $this->zatca_status,
            'cashier_name' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'items' => TransactionItemResource::collection($this->whenLoaded('transactionItems')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            // Map of product_id => total already-refunded quantity across all
            // prior non-voided return transactions against this sale. Lets the
            // UI cap per-line return inputs so cashiers can't over-refund.
            'refunded_quantities' => $this->whenLoaded('returns', function () {
                $map = [];
                foreach ($this->returns as $ret) {
                    if (($ret->status?->value ?? $ret->status) === 'voided') continue;
                    foreach ($ret->transactionItems ?? [] as $row) {
                        $pid = $row->product_id;
                        if (!$pid) continue;
                        $map[$pid] = ($map[$pid] ?? 0) + (float) $row->quantity;
                    }
                }
                return (object) $map;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
