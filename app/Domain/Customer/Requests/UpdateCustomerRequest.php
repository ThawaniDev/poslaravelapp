<?php

namespace App\Domain\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'string', 'max:255'],
            'phone'                   => ['nullable', 'string', 'max:20'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'address'                 => ['nullable', 'string', 'max:1000'],
            'date_of_birth'           => ['nullable', 'date'],
            'group_id'                => ['nullable', 'uuid'],
            'tax_registration_number' => ['nullable', 'string', 'max:50'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
