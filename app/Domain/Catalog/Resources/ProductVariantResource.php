<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_group_id' => $this->variant_group_id,
            'variant_value' => $this->variant_value,
            'variant_value_ar' => $this->variant_value_ar,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price_adjustment' => (float) $this->price_adjustment,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
