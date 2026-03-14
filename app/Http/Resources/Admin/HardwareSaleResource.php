<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HardwareSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_name' => $this->when(
                $this->relationLoaded('store'),
                fn () => $this->store?->name,
            ),
            'sold_by' => $this->sold_by,
            'sold_by_name' => $this->when(
                $this->relationLoaded('soldByAdmin'),
                fn () => $this->soldByAdmin?->name,
            ),
            'item_type' => $this->item_type,
            'item_description' => $this->item_description,
            'serial_number' => $this->serial_number,
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
            'sold_at' => $this->sold_at instanceof \DateTimeInterface
                ? $this->sold_at->toIso8601String()
                : $this->sold_at,
        ];
    }
}
