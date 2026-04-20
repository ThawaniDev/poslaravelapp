<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'staff_user_id'     => $this->staff_user_id,
            'shift_template_id' => $this->shift_template_id,
            'date'              => $this->date?->toDateString(),
            'actual_start'      => $this->actual_start?->toIso8601String(),
            'actual_end'        => $this->actual_end?->toIso8601String(),
            'status'            => $this->status?->value,
            'swapped_with_id'   => $this->swapped_with_id,
            'notes'             => $this->notes,
            'staff_user'        => new StaffUserResource($this->whenLoaded('staffUser')),
            'shift_template'    => new ShiftTemplateResource($this->whenLoaded('shiftTemplate')),
        ];
    }
}
