<?php

namespace App\Http\Requests\ProviderRegistration;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicProviderRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name'    => ['required', 'string', 'max:255'],
            'organization_name_ar' => ['nullable', 'string', 'max:255'],
            'owner_name'           => ['required', 'string', 'max:255'],
            'owner_email'          => ['required', 'email', 'max:255'],
            'owner_phone'          => ['required', 'string', 'max:20'],
            'cr_number'            => ['nullable', 'string', 'max:50'],
            'vat_number'           => ['nullable', 'string', 'max:50'],
            'business_type'        => ['nullable', 'string', 'in:grocery,restaurant,pharmacy,bakery,electronics,florist,jewelry,fashion,other'],
            'branches'             => ['nullable', 'string', 'in:1,2-5,6-20,20+'],
        ];
    }
}
