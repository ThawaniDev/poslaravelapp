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
            'softpos_enabled' => (bool) $this->softpos_enabled,
            'softpos_status'  => $this->softpos_status,
            'softpos_activated_at' => $this->softpos_activated_at?->toISOString(),
            'nearpay_tid'  => $this->nearpay_tid,
            'nearpay_mid'  => $this->nearpay_mid,

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
            'fee_profile'             => $this->fee_profile,
            'fee_mada_percentage'     => $this->fee_mada_percentage,
            'fee_visa_mc_percentage'  => $this->fee_visa_mc_percentage,
            'fee_flat_per_txn'        => $this->fee_flat_per_txn,
            'wameed_margin_percentage' => $this->wameed_margin_percentage,
            'fee_description'         => $this->fee_description,

            // Settlement
            'settlement_cycle'     => $this->settlement_cycle,
            'settlement_bank_name' => $this->settlement_bank_name,
            'settlement_iban'      => $this->settlement_iban,

            // Status
            'last_transaction_at' => $this->last_transaction_at?->toISOString(),
            'is_softpos_ready'    => $this->is_softpos_ready,
            'admin_notes'         => $this->admin_notes,

            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
