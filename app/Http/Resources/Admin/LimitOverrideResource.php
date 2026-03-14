<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LimitOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'limit_key' => $this->limit_key,
            'override_value' => $this->override_value,
            'reason' => $this->reason,
            'set_by' => $this->set_by,
            'expires_at' => $this->expires_at instanceof \DateTimeInterface
                ? $this->expires_at->toIso8601String()
                : $this->expires_at,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
        ];
    }
}
