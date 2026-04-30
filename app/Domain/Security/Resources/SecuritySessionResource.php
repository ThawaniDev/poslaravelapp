<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecuritySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'user_id'          => $this->user_id,
            'device_id'        => $this->device_id,
            'session_type'     => $this->session_type,
            'status'           => $this->status,
            'ip_address'       => $this->ip_address,
            'user_agent'       => $this->user_agent,
            'started_at'       => $this->started_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'ended_at'         => $this->ended_at?->toIso8601String(),
            'end_reason'       => $this->end_reason,
            'metadata'         => $this->metadata ?? [],
            'created_at'       => $this->started_at?->toIso8601String(),
            'updated_at'       => $this->last_activity_at?->toIso8601String(),
        ];
    }
}
