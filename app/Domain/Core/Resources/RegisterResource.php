<?php

namespace App\Domain\Core\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'store_id'     => $this->store_id,
            'name'         => $this->name,
            'device_id'    => $this->device_id,
            'app_version'  => $this->app_version,
            'platform'     => $this->platform?->value ?? $this->platform,
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'is_online'    => (bool) $this->is_online,
            'is_active'    => (bool) $this->is_active,

            // SoftPOS
            'softpos_enabled'          => (bool) $this->softpos_enabled,
            'softpos_status'           => $this->softpos_status,
            'softpos_activated_at'     => $this->softpos_activated_at?->toISOString(),
            'edfapay_token'            => $this->edfapay_token,
            'edfapay_token_updated_at' => $this->edfapay_token_updated_at?->toISOString(),

            // Device hardware
            'device_model'   => $this->device_model,
            'os_version'     => $this->os_version,
            'nfc_capable'    => (bool) $this->nfc_capable,
            'serial_number'  => $this->serial_number,

            // Status
            'last_transaction_at' => $this->last_transaction_at?->toISOString(),
            'is_softpos_ready'    => $this->is_softpos_ready,

            // Merchant-facing SoftPOS fee schedule
            // Gateway rates are internal and intentionally omitted here.
            'softpos_fees' => [
                'mada_rate'       => (float) ($this->softpos_mada_merchant_rate ?? 0.006),
                'card_fee'        => (float) ($this->softpos_card_merchant_fee  ?? 1.000),
                'mada_rate_pct'   => round((float) ($this->softpos_mada_merchant_rate ?? 0.006) * 100, 4),
            ],

            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
