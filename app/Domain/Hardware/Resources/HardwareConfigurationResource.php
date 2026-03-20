<?php

namespace App\Domain\Hardware\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HardwareConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'terminal_id'     => $this->terminal_id,
            'device_type'     => $this->device_type,
            'connection_type' => $this->connection_type,
            'device_name'     => $this->device_name,
            'config_json'     => $this->config_json,
            'is_active'       => (bool) $this->is_active,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
