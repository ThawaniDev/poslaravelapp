<?php

namespace App\Domain\Hardware\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HardwareEventLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'store_id'    => $this->store_id,
            'terminal_id' => $this->terminal_id,
            'device_type' => $this->device_type,
            'event'       => $this->event,
            'details'     => $this->details,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
