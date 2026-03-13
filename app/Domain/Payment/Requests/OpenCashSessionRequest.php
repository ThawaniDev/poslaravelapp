<?php

namespace App\Domain\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id'  => ['nullable', 'string', 'max:100'],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ];
    }
}
