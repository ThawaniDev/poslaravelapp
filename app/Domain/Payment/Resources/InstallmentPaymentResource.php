<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'transaction_id' => $this->transaction_id,
            'payment_id' => $this->payment_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'status' => $this->status?->value ?? $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'installment_count' => $this->installment_count,
            'checkout_url' => $this->checkout_url,
            'provider_order_id' => $this->provider_order_id,
            'provider_checkout_id' => $this->provider_checkout_id,
            'provider_payment_id' => $this->provider_payment_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'initiated_at' => $this->initiated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
