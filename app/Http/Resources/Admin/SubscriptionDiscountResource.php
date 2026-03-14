<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionDiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $type instanceof \BackedEnum ? $type->value : $type,
            'value' => (float) $this->value,
            'max_uses' => $this->max_uses,
            'times_used' => $this->times_used ?? 0,
            'valid_from' => $this->valid_from instanceof \DateTimeInterface
                ? $this->valid_from->toIso8601String()
                : $this->valid_from,
            'valid_to' => $this->valid_to instanceof \DateTimeInterface
                ? $this->valid_to->toIso8601String()
                : $this->valid_to,
            'applicable_plan_ids' => $this->applicable_plan_ids,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
