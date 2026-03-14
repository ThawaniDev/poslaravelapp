<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_name' => 'sometimes|string|max:50',
            'credentials' => 'sometimes|array',
            'credentials.key' => 'required_with:credentials|string',
            'credentials.secret' => 'required_with:credentials|string',
            'credentials.merchant_id' => 'nullable|string',
            'webhook_url' => 'nullable|url|max:500',
            'environment' => 'sometimes|in:sandbox,production',
            'is_active' => 'boolean',
        ];
    }
}
