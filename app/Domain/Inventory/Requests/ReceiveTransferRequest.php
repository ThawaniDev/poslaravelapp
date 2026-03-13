<?php

namespace App\Domain\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => ['required_with:items', 'uuid'],
            'items.*.quantity_received' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
