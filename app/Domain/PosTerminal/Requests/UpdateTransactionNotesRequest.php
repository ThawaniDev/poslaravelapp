<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'customer_id' => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
        ];
    }
}
