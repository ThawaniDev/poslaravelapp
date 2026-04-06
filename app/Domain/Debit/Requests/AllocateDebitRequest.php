<?php

namespace App\Domain\Debit\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AllocateDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required for allocation.',
            'order_id.exists' => 'Selected order does not exist.',
            'amount.required' => 'Allocation amount is required.',
            'amount.min' => 'Allocation amount must be at least 0.01.',
        ];
    }
}
