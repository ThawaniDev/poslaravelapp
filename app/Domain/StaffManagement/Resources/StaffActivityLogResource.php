<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'staff_user_id' => $this->staff_user_id,
            'store_id'      => $this->store_id,
            'action'        => $this->action,
            'entity_type'   => $this->entity_type,
            'entity_id'     => $this->entity_id,
            'details'       => $this->details,
            'ip_address'    => $this->ip_address,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
