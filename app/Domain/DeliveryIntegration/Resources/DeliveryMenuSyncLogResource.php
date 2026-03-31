<?php

namespace App\Domain\DeliveryIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryMenuSyncLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'platform'         => $this->platform,
            'status'           => $this->status,
            'items_synced'     => (int) $this->items_synced,
            'items_failed'     => (int) $this->items_failed,
            'error_details'    => $this->error_details,
            'triggered_by'     => $this->triggered_by,
            'sync_type'        => $this->sync_type,
            'duration_seconds' => $this->duration_seconds ? (int) $this->duration_seconds : null,
            'started_at'       => $this->started_at?->toIso8601String(),
            'completed_at'     => $this->completed_at?->toIso8601String(),
        ];
    }
}
