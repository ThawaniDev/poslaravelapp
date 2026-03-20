<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'store_id'              => $this->store_id,
            'device_name'           => $this->device_name,
            'hardware_id'           => $this->hardware_id,
            'os_info'               => $this->os_info,
            'app_version'           => $this->app_version,
            'last_active_at'        => $this->last_active_at?->toIso8601String(),
            'is_active'             => (bool) $this->is_active,
            'remote_wipe_requested' => (bool) $this->remote_wipe_requested,
            'registered_at'         => $this->registered_at?->toIso8601String(),
        ];
    }
}
