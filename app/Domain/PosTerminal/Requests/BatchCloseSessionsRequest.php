<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchCloseSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_ids' => ['sometimes', 'array'],
            'store_ids.*' => ['uuid'],
        ];
    }
}
