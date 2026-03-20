<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'user_identifier' => $this->user_identifier,
            'attempt_type'    => $this->attempt_type,
            'is_successful'   => (bool) $this->is_successful,
            'ip_address'      => $this->ip_address,
            'device_id'       => $this->device_id,
            'attempted_at'    => $this->attempted_at?->toIso8601String(),
        ];
    }
}
