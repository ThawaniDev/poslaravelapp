<?php

namespace App\Domain\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'price_adjustment' => (float) $this->price_adjustment,
            'is_default' => (bool) $this->is_default,
            'sort_order' => $this->sort_order,
        ];
    }
}
