<?php

namespace App\Domain\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
