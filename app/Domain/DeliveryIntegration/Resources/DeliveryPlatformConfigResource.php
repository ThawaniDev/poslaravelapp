<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'store_id'              => $this->store_id,
            'platform'              => $this->platform,
            'merchant_id'           => $this->merchant_id,
            'branch_id_on_platform' => $this->branch_id_on_platform,
            'is_enabled'            => (bool) $this->is_enabled,
            'auto_accept'           => (bool) $this->auto_accept,
            'throttle_limit'        => $this->throttle_limit,
            'last_menu_sync_at'     => $this->last_menu_sync_at?->toIso8601String(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
