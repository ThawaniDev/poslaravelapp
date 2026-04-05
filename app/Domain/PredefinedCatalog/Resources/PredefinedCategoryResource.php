<?php

namespace App\Domain\PredefinedCatalog\Resources;

use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredefinedCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_type_id' => $this->business_type_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'image_url' => self::resolveImageUrl($this->image_url),
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'children' => PredefinedCategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
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

    public static function resolveImageUrl(?string $value): ?string
    {
        return SupabaseStorageService::resolveUrl($value);
    }
}
