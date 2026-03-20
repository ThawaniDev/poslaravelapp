<?php

namespace App\Domain\ThawaniIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThawaniSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'settlement_date'   => $this->settlement_date,
            'gross_amount'      => (float) $this->gross_amount,
            'commission_amount' => (float) $this->commission_amount,
            'net_amount'        => (float) $this->net_amount,
            'order_count'       => (int) $this->order_count,
            'thawani_reference' => $this->thawani_reference,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
