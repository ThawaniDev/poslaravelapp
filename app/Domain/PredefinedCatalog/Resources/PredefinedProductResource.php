<?php

namespace App\Domain\PredefinedCatalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredefinedProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_type_id' => $this->business_type_id,
            'predefined_category_id' => $this->predefined_category_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'sell_price' => (float) $this->sell_price,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'unit' => $this->unit?->value,
            'tax_rate' => $this->tax_rate !== null ? (float) $this->tax_rate : null,
            'is_weighable' => (bool) $this->is_weighable,
            'tare_weight' => $this->tare_weight !== null ? (float) $this->tare_weight : null,
            'is_active' => (bool) $this->is_active,
            'age_restricted' => (bool) $this->age_restricted,
            'image_url' => PredefinedCategoryResource::resolveImageUrl($this->image_url),
            'category' => new PredefinedCategoryResource($this->whenLoaded('predefinedCategory')),
            'images' => PredefinedProductImageResource::collection($this->whenLoaded('images')),
            'business_type' => $this->whenLoaded('businessType', fn () => [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
                'name_ar' => $this->businessType->name_ar,
                'slug' => $this->businessType->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
