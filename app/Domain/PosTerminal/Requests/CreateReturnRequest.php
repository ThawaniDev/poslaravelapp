<?php

namespace App\Domain\PosTerminal\Requests;

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
            'return_transaction_id' => ['required', 'string', 'exists:transactions,id'],
            'register_id' => ['nullable', 'string'],
            'pos_session_id' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
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
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,card_mada,card_visa,card_mastercard,mada,apple_pay,stc_pay,store_credit,gift_card,mobile_payment,loyalty_points,bank_transfer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.change_given' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
