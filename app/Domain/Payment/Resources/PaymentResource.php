<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'transaction_id'     => $this->transaction_id,
            'method'             => $this->method?->value ?? $this->method,
            'amount'             => (float) $this->amount,
            'cash_tendered'      => $this->cash_tendered !== null ? (float) $this->cash_tendered : null,
            'change_given'       => $this->change_given !== null ? (float) $this->change_given : null,
            'tip_amount'         => $this->tip_amount !== null ? (float) $this->tip_amount : null,
            'card_brand'         => $this->card_brand,
            'card_last_four'     => $this->card_last_four,
            'card_auth_code'     => $this->card_auth_code,
            'card_reference'     => $this->card_reference,
            'gift_card_code'     => $this->gift_card_code,
            'coupon_code'        => $this->coupon_code,
            'loyalty_points_used' => $this->loyalty_points_used,
        ];
    }
}
