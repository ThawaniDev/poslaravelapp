<?php

namespace App\Domain\PosTerminal\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'product_id' => $this->product_id,
            'barcode' => $this->barcode,
            'product_name' => $this->product_name,
            'product_name_ar' => $this->product_name_ar,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
            'is_return_item' => (bool) $this->is_return_item,
            'modifier_total' => (float) ($this->modifier_total ?? 0),
            'modifier_selections' => $this->modifier_selections,
            'item_notes' => $this->item_notes,
            'notes' => $this->notes,
        ];
    }
}
