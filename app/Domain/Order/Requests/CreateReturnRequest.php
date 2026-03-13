<?php

namespace App\Domain\Order\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:full,partial'],
            'reason_code' => ['nullable', 'string'],
            'refund_method' => ['nullable', 'string', 'in:original_method,cash,store_credit'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_refund' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['nullable', 'string'],
            'items.*.product_id' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.refund_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
