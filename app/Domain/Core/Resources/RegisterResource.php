<?php

namespace App\Domain\Core\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    /**
     * Provider-facing resource — basic terminal info only.
     * SoftPOS details are hidden from providers.
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'store_id'     => $this->store_id,
            'name'         => $this->name,
            'device_id'    => $this->device_id,
            'app_version'  => $this->app_version,
            'platform'     => $this->platform?->value ?? $this->platform,
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'is_online'    => (bool) $this->is_online,
            'is_active'    => (bool) $this->is_active,
            'softpos_enabled' => (bool) $this->softpos_enabled,
            'softpos_status'  => $this->softpos_status,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
