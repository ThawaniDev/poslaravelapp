<?php

namespace App\Domain\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash'  => ['required', 'numeric', 'min:0'],
            'close_notes'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
