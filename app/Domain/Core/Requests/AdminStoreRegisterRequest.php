<?php

namespace App\Domain\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic terminal
            'store_id'    => ['required', 'uuid', 'exists:stores,id'],
            'name'        => ['required', 'string', 'max:100'],
            'device_id'   => ['required', 'string', 'max:100', 'unique:registers,device_id'],
            'platform'    => ['required', 'string', 'in:windows,macos,ios,android'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'is_active'   => ['sometimes', 'boolean'],

            // SoftPOS
            'softpos_enabled'   => ['sometimes', 'boolean'],
            'softpos_provider'  => ['nullable', 'string', 'in:nearpay,edfapay'],
            'nearpay_tid'       => ['nullable', 'string', 'max:50'],
            'nearpay_mid'       => ['nullable', 'string', 'max:50'],
            'nearpay_auth_key'  => ['nullable', 'string', 'max:255'],
            'edfapay_token'     => ['nullable', 'string', 'max:500'],

            // Acquirer
            'acquirer_source'    => ['nullable', 'string', Rule::in(['hala', 'bank_rajhi', 'bank_snb', 'geidea', 'other'])],
            'acquirer_name'      => ['nullable', 'string', 'max:100'],
            'acquirer_reference' => ['nullable', 'string', 'max:100'],

            // Device hardware
            'device_model'   => ['nullable', 'string', 'max:100'],
            'os_version'     => ['nullable', 'string', 'max:30'],
            'nfc_capable'    => ['sometimes', 'boolean'],
            'serial_number'  => ['nullable', 'string', 'max:100'],

            // Fee config
            'fee_profile'              => ['sometimes', 'string', Rule::in(['standard', 'custom', 'promotional'])],
            'fee_mada_percentage'      => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'fee_visa_mc_percentage'   => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'fee_flat_per_txn'         => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'wameed_margin_percentage' => ['sometimes', 'numeric', 'min:0', 'max:1'],

            // Settlement
            'settlement_cycle'     => ['sometimes', 'string', 'max:10'],
            'settlement_bank_name' => ['nullable', 'string', 'max:100'],
            'settlement_iban'      => ['nullable', 'string', 'max:34'],

            // Status
            'softpos_status' => ['sometimes', 'string', Rule::in(['pending', 'active', 'suspended', 'deactivated'])],
            'admin_notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required'      => __('terminals.store_required'),
            'store_id.exists'        => __('terminals.store_not_found'),
            'name.required'          => __('terminals.name_required'),
            'device_id.required'     => __('terminals.device_id_required'),
            'device_id.unique'       => __('terminals.device_id_taken'),
            'platform.required'      => __('terminals.platform_required'),
            'platform.in'            => __('terminals.platform_invalid'),
            'acquirer_source.in'     => __('terminals.acquirer_source_invalid'),
            'softpos_status.in'      => __('terminals.softpos_status_invalid'),
            'fee_profile.in'         => __('terminals.fee_profile_invalid'),
            'settlement_iban.max'    => __('terminals.iban_too_long'),
        ];
    }
}
