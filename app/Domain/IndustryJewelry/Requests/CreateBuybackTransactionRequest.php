<?php

namespace App\Domain\IndustryJewelry\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBuybackTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => ['nullable', 'uuid'],
            'metal_type'     => ['required', 'string', 'in:gold,silver,platinum,palladium'],
            'karat'          => ['required', 'integer', 'in:8,9,10,14,18,21,22,24'],
            'weight_g'       => ['required', 'numeric', 'min:0.01'],
            'rate_per_gram'  => ['required', 'numeric', 'min:0'],
            'total_amount'   => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,store_credit'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
