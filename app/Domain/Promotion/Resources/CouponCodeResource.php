<?php

namespace App\Domain\Promotion\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'promotion_id' => $this->promotion_id,
            'code'         => $this->code,
            'max_uses'     => (int) $this->max_uses,
            'usage_count'  => (int) $this->usage_count,
            'is_active'    => (bool) $this->is_active,
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
