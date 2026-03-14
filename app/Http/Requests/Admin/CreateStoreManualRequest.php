<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_business_type' => ['nullable', 'string', 'max:50'],
            'organization_country' => ['nullable', 'string', 'max:5'],
            'store_name' => ['required', 'string', 'max:255'],
            'store_business_type' => ['nullable', 'string', 'max:50'],
            'store_currency' => ['nullable', 'string', 'max:5'],
            'store_is_active' => ['nullable', 'boolean'],
        ];
    }
}
