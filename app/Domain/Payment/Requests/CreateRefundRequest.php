<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\PaymentMethodKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'method'           => ['nullable', Rule::enum(PaymentMethodKey::class)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
