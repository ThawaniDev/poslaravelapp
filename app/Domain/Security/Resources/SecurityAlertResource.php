<?php

namespace App\Domain\Security\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'admin_user_id'    => $this->admin_user_id,
            'alert_type'       => $this->alert_type,
            'severity'         => $this->severity,
            'description'      => $this->description,
            'ip_address'       => $this->ip_address,
            'details'          => $this->details ?? [],
            'status'           => $this->status,
            'resolved_at'      => $this->resolved_at?->toIso8601String(),
            'resolved_by'      => $this->resolved_by,
            'resolution_notes' => $this->resolution_notes,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
