<?php

namespace App\Domain\IndustryFlorist\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FlowerArrangementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'store_id'    => $this->store_id,
            'name'        => $this->name,
            'occasion'    => $this->occasion,
            'items_json'  => $this->items_json,
            'total_price' => $this->total_price,
            'is_template' => $this->is_template,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
