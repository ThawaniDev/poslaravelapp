<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImplementationFeeResource extends JsonResource
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
            'fee_type' => $this->fee_type,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
        ];
    }
}
