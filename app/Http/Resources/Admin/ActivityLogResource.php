<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at;
        if ($createdAt instanceof \DateTimeInterface) {
            $createdAt = $createdAt->format('c');
        }

        return [
            'id' => $this->id,
            'admin_user_id' => $this->admin_user_id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $createdAt,
        ];
    }
}
