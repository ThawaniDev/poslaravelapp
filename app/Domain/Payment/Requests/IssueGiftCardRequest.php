<?php

namespace App\Domain\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric', 'min:1'],
            'code'           => ['nullable', 'string', 'max:50', 'unique:gift_cards,code'],
            'barcode'        => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'expires_at'     => ['nullable', 'date', 'after:today'],
        ];
    }
}
