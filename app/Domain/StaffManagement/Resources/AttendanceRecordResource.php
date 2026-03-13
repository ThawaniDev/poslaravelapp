<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'staff_user_id'     => $this->staff_user_id,
            'store_id'          => $this->store_id,
            'clock_in_at'       => $this->clock_in_at?->toIso8601String(),
            'clock_out_at'      => $this->clock_out_at?->toIso8601String(),
            'break_minutes'     => (int) ($this->break_minutes ?? 0),
            'overtime_minutes'  => (int) ($this->overtime_minutes ?? 0),
            'scheduled_shift_id' => $this->scheduled_shift_id,
            'notes'             => $this->notes,
            'auth_method'       => $this->auth_method?->value,
            'staff_user'        => new StaffUserResource($this->whenLoaded('staffUser')),
            'break_records'     => BreakRecordResource::collection($this->whenLoaded('breakRecords')),
        ];
    }
}
