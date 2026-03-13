<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => ['nullable', 'string'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ];
    }
}
