<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'sync_version' => $this->sync_version,
            'children' => CategoryResource::collection($this->whenLoaded('categories')),
            'products_count' => $this->resource->getAttributes()['products_count'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
