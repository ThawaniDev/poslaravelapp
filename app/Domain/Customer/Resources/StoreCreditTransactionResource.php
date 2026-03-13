<?php

namespace App\Domain\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'customer_id'   => $this->customer_id,
            'type'          => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'amount'        => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'order_id'      => $this->order_id,
            'payment_id'    => $this->payment_id,
            'notes'         => $this->notes,
            'performed_by'  => $this->performed_by,
            'created_at'    => $this->created_at,
        ];
    }
}
