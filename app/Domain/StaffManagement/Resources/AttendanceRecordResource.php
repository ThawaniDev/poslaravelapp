<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clockIn = $this->clock_in_at;
        $clockOut = $this->clock_out_at;

        // Calculate worked minutes
        if ($clockIn && $clockOut) {
            $rawMinutes = (int) round($clockOut->diffInSeconds($clockIn) / 60);
            $workMinutes = max(0, $rawMinutes - (int) ($this->break_minutes ?? 0));
        } elseif ($clockIn) {
            $rawMinutes = (int) round(now()->diffInSeconds($clockIn) / 60);
            $workMinutes = max(0, $rawMinutes - (int) ($this->break_minutes ?? 0));
        } else {
            $workMinutes = 0;
        }

        return [
            'id'                  => $this->id,
            'staff_user_id'       => $this->staff_user_id,
            'store_id'            => $this->store_id,
            'clock_in_at'         => $clockIn?->toIso8601String(),
            'clock_out_at'        => $clockOut?->toIso8601String(),
            'break_minutes'       => (int) ($this->break_minutes ?? 0),
            'work_minutes'        => $workMinutes,
            'overtime_minutes'    => (int) ($this->overtime_minutes ?? 0),
            'scheduled_shift_id'  => $this->scheduled_shift_id,
            'notes'               => $this->notes,
            'auth_method'         => $this->auth_method?->value,
            'status'              => $this->status,
            'created_at'          => isset($this->created_at) ? $this->created_at : null,
            'staff_user'          => new StaffUserResource($this->whenLoaded('staffUser')),
            'break_records'       => BreakRecordResource::collection($this->whenLoaded('breakRecords')),
        ];
    }
}
