<?php

namespace App\Domain\CashierGamification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashierBadgeAwardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'cashier_id' => $this->cashier_id,
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
                'email' => $this->cashier->email,
            ]),
            'badge_id' => $this->badge_id,
            'badge' => $this->whenLoaded('badge', fn () => new CashierBadgeResource($this->badge)),
            'snapshot_id' => $this->snapshot_id,
            'earned_date' => $this->earned_date,
            'period' => $this->period?->value ?? $this->period,
            'metric_value' => (float) $this->metric_value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
