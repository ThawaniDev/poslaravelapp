<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'attendance_record_id' => $this->attendance_record_id,
            'break_start'          => $this->break_start?->toIso8601String(),
            'break_end'            => $this->break_end?->toIso8601String(),
        ];
    }
}
