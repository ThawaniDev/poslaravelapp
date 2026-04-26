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
            'planned_batches' => $this->planned_batches !== null ? (int) $this->planned_batches : null,
            'actual_batches'  => $this->actual_batches !== null ? (int) $this->actual_batches : null,
            'planned_yield'   => $this->planned_yield !== null ? (int) $this->planned_yield : null,
            'actual_yield'    => $this->actual_yield !== null ? (int) $this->actual_yield : null,
            'status'          => $this->status,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
