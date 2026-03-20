<?php

namespace App\Domain\IndustryJewelry\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyMetalRateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'store_id'              => $this->store_id,
            'metal_type'            => $this->metal_type?->value,
            'karat'                 => $this->karat,
            'rate_per_gram'         => $this->rate_per_gram,
            'buyback_rate_per_gram' => $this->buyback_rate_per_gram,
            'effective_date'        => $this->effective_date?->toDateString(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
