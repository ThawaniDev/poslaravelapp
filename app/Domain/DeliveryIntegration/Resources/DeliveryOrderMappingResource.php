<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_id'          => $this->order_id,
            'platform'          => $this->platform,
            'external_order_id' => $this->external_order_id,
            'external_status'   => $this->external_status,
            'commission_amount' => $this->commission_amount ? (float) $this->commission_amount : null,
            'commission_percent'=> $this->commission_percent ? (float) $this->commission_percent : null,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
