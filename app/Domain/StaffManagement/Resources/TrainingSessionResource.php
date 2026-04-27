<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $durationMinutes = null;

        if ($this->started_at) {
            $end = $this->ended_at ?? now();
            $durationMinutes = (int) round($this->started_at->diffInSeconds($end) / 60);
        }

        return [
            'id'                   => $this->id,
            'staff_user_id'        => $this->staff_user_id,
            'store_id'             => $this->store_id,
            'started_at'           => $this->started_at?->toIso8601String(),
            'ended_at'             => $this->ended_at?->toIso8601String(),
            'duration_minutes'     => $durationMinutes,
            'is_active'            => $this->ended_at === null,
            'transactions_count'   => (int) ($this->transactions_count ?? 0),
            'notes'                => $this->notes,
            'staff_user'           => new StaffUserResource($this->whenLoaded('staffUser')),
        ];
    }
}
