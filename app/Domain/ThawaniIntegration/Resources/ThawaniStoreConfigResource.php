<?php

namespace App\Domain\ThawaniIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThawaniStoreConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'store_id'             => $this->store_id,
            'thawani_store_id'     => $this->thawani_store_id,
            'is_connected'         => (bool) $this->is_connected,
            'auto_sync_products'   => (bool) $this->auto_sync_products,
            'auto_sync_inventory'  => (bool) $this->auto_sync_inventory,
            'auto_accept_orders'   => (bool) $this->auto_accept_orders,
            'operating_hours_json' => $this->operating_hours_json,
            'commission_rate'      => $this->commission_rate ? (float) $this->commission_rate : null,
            'connected_at'         => $this->connected_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
