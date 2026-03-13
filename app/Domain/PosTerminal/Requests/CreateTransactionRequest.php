<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => ['nullable', 'string'],
            'pos_session_id' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'string'],
            'transaction_number' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:sale,return,void,exchange'],
            'status' => ['nullable', 'string', 'in:completed,voided,pending'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'is_tax_exempt' => ['nullable', 'boolean'],
            'return_transaction_id' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'string'],
            'items.*.barcode' => ['nullable', 'string'],
            'items.*.product_name' => ['required', 'string'],
            'items.*.product_name_ar' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['required', 'numeric'],
            'items.*.is_return_item' => ['nullable', 'boolean'],
        ];
    }
}
