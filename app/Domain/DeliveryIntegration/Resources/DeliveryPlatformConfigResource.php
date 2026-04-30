<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'store_id'                   => $this->store_id,
            'platform'                   => $this->platform,
            'merchant_id'                => $this->merchant_id,
            'branch_id_on_platform'      => $this->branch_id_on_platform,
            'is_enabled'                 => (bool) $this->is_enabled,
            'has_api_key'                => ! empty($this->api_key),
            'auto_accept'                => (bool) $this->auto_accept,
            'auto_accept_timeout_seconds'=> (int) ($this->auto_accept_timeout_seconds ?? 300),
            'throttle_limit'             => $this->throttle_limit,
            'max_daily_orders'           => $this->max_daily_orders,
            'daily_order_count'          => $this->daily_order_count ?? 0,
            'sync_menu_on_product_change'=> (bool) ($this->sync_menu_on_product_change ?? false),
            'menu_sync_interval_hours'   => $this->menu_sync_interval_hours,
            'operating_hours_synced'     => (bool) ($this->operating_hours_synced ?? false),
            'operating_hours_json'       => is_string($this->operating_hours_json)
                ? json_decode($this->operating_hours_json, true)
                : $this->operating_hours_json,
            'webhook_url'                => $this->webhook_url,
            'status'                     => $this->status ?? 'pending',
            'last_menu_sync_at'          => $this->last_menu_sync_at?->toIso8601String(),
            'last_order_received_at'     => $this->last_order_received_at?->toIso8601String(),
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
