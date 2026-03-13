<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\PaymentMethodKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id'     => ['required', 'uuid', 'exists:transactions,id'],
            'method'             => ['required', new Enum(PaymentMethodKey::class)],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'cash_tendered'      => ['nullable', 'numeric', 'min:0'],
            'change_given'       => ['nullable', 'numeric', 'min:0'],
            'tip_amount'         => ['nullable', 'numeric', 'min:0'],
            'card_brand'         => ['nullable', 'string', 'max:50'],
            'card_last_four'     => ['nullable', 'string', 'size:4'],
            'card_auth_code'     => ['nullable', 'string', 'max:50'],
            'card_reference'     => ['nullable', 'string', 'max:100'],
            'gift_card_code'     => ['nullable', 'string', 'max:50'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
            'loyalty_points_used' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
