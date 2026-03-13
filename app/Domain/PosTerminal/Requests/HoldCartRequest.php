<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'string'],
            'cart_data' => ['required', 'array'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
