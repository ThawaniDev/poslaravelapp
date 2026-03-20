<?php

namespace App\Domain\IndustryJewelry\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JewelryProductDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'product_id'           => $this->product_id,
            'metal_type'           => $this->metal_type?->value,
            'karat'                => $this->karat,
            'gross_weight_g'       => $this->gross_weight_g,
            'net_weight_g'         => $this->net_weight_g,
            'making_charges_type'  => $this->making_charges_type?->value,
            'making_charges_value' => $this->making_charges_value,
            'stone_type'           => $this->stone_type,
            'stone_weight_carat'   => $this->stone_weight_carat,
            'stone_count'          => $this->stone_count,
            'certificate_number'   => $this->certificate_number,
            'certificate_url'      => $this->certificate_url,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
