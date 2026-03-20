<?php

namespace App\Domain\IndustryPharmacy\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DrugScheduleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'product_id'            => $this->product_id,
            'schedule_type'         => $this->schedule_type?->value,
            'active_ingredient'     => $this->active_ingredient,
            'dosage_form'           => $this->dosage_form,
            'strength'              => $this->strength,
            'manufacturer'          => $this->manufacturer,
            'requires_prescription' => $this->requires_prescription,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
