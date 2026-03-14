<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateGatewayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_name' => 'required|string|max:50',
            'credentials' => 'required|array',
            'credentials.key' => 'required|string',
            'credentials.secret' => 'required|string',
            'credentials.merchant_id' => 'nullable|string',
            'webhook_url' => 'nullable|url|max:500',
            'environment' => 'required|in:sandbox,production',
            'is_active' => 'boolean',
        ];
    }
}
