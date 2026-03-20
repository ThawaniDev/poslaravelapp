<?php

namespace App\Domain\Promotion\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionUsageLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'promotion_id'    => $this->promotion_id,
            'coupon_code_id'  => $this->coupon_code_id,
            'order_id'        => $this->order_id,
            'customer_id'     => $this->customer_id,
            'discount_amount' => $this->discount_amount,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
