<?php

namespace App\Domain\ThawaniIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThawaniProductMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'store_id'           => $this->store_id,
            'product_id'         => $this->product_id,
            'thawani_product_id' => $this->thawani_product_id,
            'is_published'       => (bool) $this->is_published,
            'online_price'       => $this->online_price ? (float) $this->online_price : null,
            'display_order'      => (int) $this->display_order,
            'last_synced_at'     => $this->last_synced_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
