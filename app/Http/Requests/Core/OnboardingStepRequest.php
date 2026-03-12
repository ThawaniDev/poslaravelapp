<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'step' => ['required', 'string', 'in:welcome,business_info,business_type,tax,hardware,products,staff,review'],
            'data' => ['sometimes', 'array'],
            // Step-specific validation
            'data.business_type' => ['sometimes', 'string'],
            'data.name' => ['sometimes', 'string', 'max:255'],
            'data.name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'data.email' => ['sometimes', 'nullable', 'email'],
            'data.address' => ['sometimes', 'nullable', 'string'],
            'data.city' => ['sometimes', 'nullable', 'string'],
            'data.cr_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data.vat_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'data.tax_label' => ['sometimes', 'string', 'max:50'],
            'data.tax_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'data.tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'data.prices_include_tax' => ['sometimes', 'boolean'],
        ];
    }
}
