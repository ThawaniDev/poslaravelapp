<?php

namespace App\Domain\Core\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminRegisterResource extends JsonResource
{
    /**
     * Admin-facing resource — includes all SoftPOS / fee / acquirer details.
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'store_id'     => $this->store_id,
            'store'        => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
            ]),
            'name'         => $this->name,
            'device_id'    => $this->device_id,
            'app_version'  => $this->app_version,
            'platform'     => $this->platform?->value ?? $this->platform,
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'is_online'    => (bool) $this->is_online,
            'is_active'    => (bool) $this->is_active,

            // SoftPOS core
            'softpos_enabled'          => (bool) $this->softpos_enabled,
            'softpos_provider'         => $this->softpos_provider,
            'nearpay_tid'              => $this->nearpay_tid,
            'nearpay_mid'              => $this->nearpay_mid,
            'edfapay_token'            => $this->edfapay_token,
            // nearpay_auth_key is hidden ($hidden on model) — never exposed

            // Acquirer
            'acquirer_source'    => $this->acquirer_source,
            'acquirer_name'      => $this->acquirer_name,
            'acquirer_reference' => $this->acquirer_reference,

            // Device hardware
            'device_model'   => $this->device_model,
            'os_version'     => $this->os_version,
            'nfc_capable'    => (bool) $this->nfc_capable,
            'serial_number'  => $this->serial_number,

            // Fee config
            'fee_profile'              => $this->fee_profile,
            'fee_mada_percentage'      => (float) $this->fee_mada_percentage,
            'fee_visa_mc_percentage'   => (float) $this->fee_visa_mc_percentage,
            'fee_flat_per_txn'         => (float) $this->fee_flat_per_txn,
            'wameed_margin_percentage' => (float) $this->wameed_margin_percentage,
            'fee_description'          => $this->fee_description,

            // Bilateral SoftPOS billing rates (full breakdown — admin only)
            'softpos_billing' => [
                // Mada — percentage-based
                'mada_merchant_rate'      => (float) ($this->softpos_mada_merchant_rate ?? 0.006),
                'mada_gateway_rate'       => (float) ($this->softpos_mada_gateway_rate  ?? 0.004),
                'mada_margin_rate'        => round(
                    (float) ($this->softpos_mada_merchant_rate ?? 0.006) -
                    (float) ($this->softpos_mada_gateway_rate  ?? 0.004),
                    6
                ),
                'mada_merchant_rate_pct'  => round((float) ($this->softpos_mada_merchant_rate ?? 0.006) * 100, 4),
                'mada_gateway_rate_pct'   => round((float) ($this->softpos_mada_gateway_rate  ?? 0.004) * 100, 4),
                'mada_margin_rate_pct'    => round((
                    (float) ($this->softpos_mada_merchant_rate ?? 0.006) -
                    (float) ($this->softpos_mada_gateway_rate  ?? 0.004)
                ) * 100, 4),
                // Visa / Mastercard — percentage + fixed per transaction
                'card_merchant_rate'      => (float) ($this->softpos_card_merchant_rate ?? 0.0),
                'card_gateway_rate'       => (float) ($this->softpos_card_gateway_rate  ?? 0.0),
                'card_merchant_rate_pct'  => round((float) ($this->softpos_card_merchant_rate ?? 0.0) * 100, 4),
                'card_gateway_rate_pct'   => round((float) ($this->softpos_card_gateway_rate  ?? 0.0) * 100, 4),
                'card_merchant_fee'       => (float) ($this->softpos_card_merchant_fee ?? 1.000),
                'card_gateway_fee'        => (float) ($this->softpos_card_gateway_fee  ?? 0.500),
                'card_margin_fee'         => round(
                    (float) ($this->softpos_card_merchant_fee ?? 1.000) -
                    (float) ($this->softpos_card_gateway_fee  ?? 0.500),
                    3
                ),
            ],

            // Settlement
            'settlement_cycle'     => $this->settlement_cycle,
            'settlement_bank_name' => $this->settlement_bank_name,
            'settlement_iban'      => $this->settlement_iban,

            // Status
            'softpos_status'           => $this->softpos_status,
            'softpos_activated_at'     => $this->softpos_activated_at?->toISOString(),
            'edfapay_token_updated_at' => $this->edfapay_token_updated_at?->toISOString(),
            'last_transaction_at'      => $this->last_transaction_at?->toISOString(),
            'is_softpos_ready'         => $this->is_softpos_ready,
            'admin_notes'              => $this->admin_notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
