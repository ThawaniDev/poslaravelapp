<?php

namespace App\Domain\AccountingIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:quickbooks,xero,qoyod',
            'access_token' => 'required|string',
            'refresh_token' => 'required|string',
            'token_expires_at' => 'required|date',
            'realm_id' => 'nullable|string|max:50',
            'tenant_id' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
        ];
    }
}
