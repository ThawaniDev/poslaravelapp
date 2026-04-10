<?php

namespace App\Domain\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description_ar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_under_maintenance' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'maintenance_message_ar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'supported_currencies' => ['sometimes', 'array'],
            'supported_currencies.*' => ['string', 'size:3'],
            'min_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'supported_installment_counts' => ['sometimes', 'array'],
            'supported_installment_counts.*' => ['integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
