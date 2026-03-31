<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StocktakeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'reference_number' => $this->reference_number,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'category_id' => $this->category_id,
            'notes' => $this->notes,
            'started_by' => $this->started_by,
            'completed_by' => $this->completed_by,
            'completed_at' => $this->completed_at?->toIso8601String(),

            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'items' => StocktakeItemResource::collection($this->whenLoaded('stocktakeItems')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
