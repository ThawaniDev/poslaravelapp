<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'user_id'       => $this->user_id,
            'user_type'     => $this->user_type,
            'action'        => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id'   => $this->resource_id,
            'details'       => $this->details,
            'severity'      => $this->severity,
            'ip_address'    => $this->ip_address,
            'device_id'     => $this->device_id,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
