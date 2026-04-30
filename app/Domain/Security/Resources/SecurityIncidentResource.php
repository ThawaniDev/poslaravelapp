<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->store_id,
            'type'             => $this->incident_type,   // normalized: incident_type → type
            'incident_type'    => $this->incident_type,   // keep both for compatibility
            'severity'         => $this->severity,
            'title'            => $this->title,
            'description'      => $this->description,
            'user_id'          => $this->user_id,
            'device_id'        => $this->device_id,
            'ip_address'       => $this->source_ip,
            'details'          => $this->metadata ?? [],
            'status'           => $this->status,
            'is_resolved'      => $this->status === 'resolved',   // computed bool for Flutter
            'resolved_by'      => $this->resolved_by,
            'resolved_at'      => $this->resolved_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
