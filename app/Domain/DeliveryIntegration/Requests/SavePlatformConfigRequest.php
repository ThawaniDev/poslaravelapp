<?php

namespace App\Domain\DeliveryIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePlatformConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform'              => ['required', 'string', 'max:50'],
            'api_key'               => ['required', 'string'],
            'merchant_id'           => ['nullable', 'string', 'max:100'],
            'webhook_secret'        => ['nullable', 'string'],
            'branch_id_on_platform' => ['nullable', 'string', 'max:100'],
            'is_enabled'            => ['sometimes', 'boolean'],
            'auto_accept'           => ['sometimes', 'boolean'],
            'throttle_limit'        => ['nullable', 'integer', 'min:1'],
        ];
    }
}
