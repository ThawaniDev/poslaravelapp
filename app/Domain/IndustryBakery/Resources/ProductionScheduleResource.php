<?php

namespace App\Domain\IndustryBakery\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'recipe_id'       => $this->recipe_id,
            'schedule_date'   => $this->schedule_date,
            'planned_batches' => (int) $this->planned_batches,
            'actual_batches'  => $this->actual_batches ? (int) $this->actual_batches : null,
            'planned_yield'   => $this->planned_yield,
            'actual_yield'    => $this->actual_yield,
            'status'          => $this->status,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
