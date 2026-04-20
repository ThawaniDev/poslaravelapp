<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'store_id'               => $this->store_id,
            'name'                   => $this->name,
            'start_time'             => $this->start_time,
            'end_time'               => $this->end_time,
            'break_duration_minutes' => (int) ($this->break_duration_minutes ?? 0),
            'color'                  => $this->color,
            'is_active'              => (bool) ($this->is_active ?? true),
        ];
    }
}
