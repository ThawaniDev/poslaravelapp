<?php

namespace App\Domain\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registerId = $this->route('register');

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'device_id'   => ['sometimes', 'required', 'string', 'max:100', Rule::unique('registers', 'device_id')->ignore($registerId)],
            'platform'    => ['sometimes', 'required', 'string', 'in:windows,macos,ios,android'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'is_active'   => ['sometimes', 'boolean'],

            // Device hardware
            'device_model'  => ['nullable', 'string', 'max:100'],
            'os_version'    => ['nullable', 'string', 'max:50'],
            'nfc_capable'   => ['nullable', 'boolean'],
            'serial_number' => ['nullable', 'string', 'max:100'],

            // SoftPOS
            'softpos_enabled' => ['nullable', 'boolean'],
            'nearpay_tid'     => ['nullable', 'string', 'max:50'],
            'nearpay_mid'     => ['nullable', 'string', 'max:50'],

            // Acquirer
            'acquirer_source'    => ['nullable', 'string', 'in:hala,bank_rajhi,bank_snb,geidea,other'],
            'acquirer_name'      => ['nullable', 'string', 'max:100'],
            'acquirer_reference' => ['nullable', 'string', 'max:100'],

            // Settlement
            'settlement_cycle'     => ['nullable', 'string', 'in:T+0,T+1,T+2,T+3,weekly'],
            'settlement_bank_name' => ['nullable', 'string', 'max:100'],
            'settlement_iban'      => ['nullable', 'string', 'max:34'],

            // Notes
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
