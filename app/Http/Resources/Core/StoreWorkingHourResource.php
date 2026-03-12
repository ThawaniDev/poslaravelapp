<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreWorkingHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->dayName(),
            'is_open' => $this->is_open,
            'open_time' => $this->open_time,
            'close_time' => $this->close_time,
            'break_start' => $this->break_start,
            'break_end' => $this->break_end,
        ];
    }
}
