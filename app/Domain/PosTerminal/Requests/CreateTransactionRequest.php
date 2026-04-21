<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // Payments
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,card_mada,card_visa,card_mastercard,mada,apple_pay,stc_pay,store_credit,gift_card,mobile_payment,loyalty_points,bank_transfer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.change_given' => ['nullable', 'numeric', 'min:0'],
            'payments.*.tip_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.card_brand' => ['nullable', 'string'],
            'payments.*.card_last_four' => ['nullable', 'string', 'size:4'],
            'payments.*.card_auth_code' => ['nullable', 'string'],
            'payments.*.card_reference' => ['nullable', 'string'],
            'payments.*.gift_card_code' => ['nullable', 'string'],
            'payments.*.coupon_code' => ['nullable', 'string'],
            'payments.*.loyalty_points_used' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $totalAmount = (float) ($this->input('total_amount', 0));
            $paymentSum = collect($this->input('payments', []))
                ->sum(fn ($p) => (float) ($p['amount'] ?? 0));

            if ($paymentSum < $totalAmount - 0.01) {
                $validator->errors()->add('payments', __('pos.payment_total_insufficient'));
            }

            // Validate return qty doesn't exceed original transaction qty
            if ($this->input('type') === 'return' && $this->input('return_transaction_id')) {
                $original = \App\Domain\PosTerminal\Models\Transaction::with('transactionItems')
                    ->find($this->input('return_transaction_id'));
                if ($original) {
                    foreach ($this->input('items', []) as $idx => $item) {
                        if (empty($item['product_id'])) {
                            continue;
                        }
                        $originalItem = $original->transactionItems
                            ->firstWhere('product_id', $item['product_id']);
                        if ($originalItem && (float) ($item['quantity'] ?? 0) > (float) $originalItem->quantity) {
                            $validator->errors()->add(
                                "items.{$idx}.quantity",
                                __('pos.return_qty_exceeds_original', [
                                    'product' => $item['product_name'] ?? '',
                                    'max' => $originalItem->quantity,
                                ])
                            );
                        }
                    }
                }
            }
        });
    }
}
