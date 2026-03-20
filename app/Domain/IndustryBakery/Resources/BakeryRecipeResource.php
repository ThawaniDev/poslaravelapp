<?php

namespace App\Domain\IndustryBakery\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BakeryRecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'store_id'           => $this->store_id,
            'product_id'         => $this->product_id,
            'name'               => $this->name,
            'expected_yield'     => $this->expected_yield,
            'prep_time_minutes'  => (int) $this->prep_time_minutes,
            'bake_time_minutes'  => (int) $this->bake_time_minutes,
            'bake_temperature_c' => $this->bake_temperature_c,
            'instructions'       => $this->instructions,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
