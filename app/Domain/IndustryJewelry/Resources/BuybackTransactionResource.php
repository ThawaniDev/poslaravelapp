<?php

namespace App\Domain\IndustryJewelry\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BuybackTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'store_id'       => $this->store_id,
            'customer_id'    => $this->customer_id,
            'metal_type'     => $this->metal_type?->value,
            'karat'          => $this->karat,
            'weight_g'       => $this->weight_g,
            'rate_per_gram'  => $this->rate_per_gram,
            'total_amount'   => $this->total_amount,
            'payment_method' => $this->payment_method?->value,
            'staff_user_id'  => $this->staff_user_id,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
