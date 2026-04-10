<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIFeatureDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'name'           => $this->name,
            'name_ar'        => $this->name_ar,
            'description'    => $this->description,
            'description_ar' => $this->description_ar,
            'category'       => $this->category?->value ?? $this->category,
            'icon'           => $this->icon,
            'default_model'  => $this->default_model,
            'is_enabled'     => (bool) $this->is_enabled,
            'is_premium'     => (bool) $this->is_premium,
            'daily_limit'    => (int) $this->daily_limit,
            'monthly_limit'  => (int) $this->monthly_limit,
            'sort_order'     => (int) $this->sort_order,
            'store_configs'  => $this->whenLoaded('storeConfigs', fn () =>
                AIStoreFeatureConfigResource::collection($this->storeConfigs)
            ),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
