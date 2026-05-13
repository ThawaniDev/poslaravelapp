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
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:sale,return,void,exchange'],
            'status' => ['nullable', 'string', 'in:completed,voided,pending'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'is_tax_exempt' => ['nullable', 'boolean'],
            'tax_exemption' => ['nullable', 'array'],
            'tax_exemption.exemption_type' => ['required_with:tax_exemption', 'string'],
            'tax_exemption.customer_tax_id' => ['nullable', 'string', 'max:50'],
            'tax_exemption.certificate_number' => ['nullable', 'string', 'max:100'],
            'tax_exemption.notes' => ['nullable', 'string', 'max:500'],
            'approval_token' => ['nullable', 'string'],
            'return_transaction_id' => ['nullable', 'string'],
            'tab_id' => ['nullable', 'string'],
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
            'items.*.age_verified' => ['nullable', 'boolean'],
            'items.*.item_notes' => ['nullable', 'string', 'max:500'],
            // Product modifiers (add-ons / option groups). Each entry is the
            // resolved modifier_option_id + cached name + price_adjustment so
            // the receipt remains accurate even if the option later changes.
            'items.*.modifier_selections' => ['nullable', 'array'],
            'items.*.modifier_selections.*.modifier_option_id' => ['required_with:items.*.modifier_selections', 'string'],
            'items.*.modifier_selections.*.modifier_group_id' => ['nullable', 'string'],
            'items.*.modifier_selections.*.name' => ['nullable', 'string', 'max:200'],
            'items.*.modifier_selections.*.name_ar' => ['nullable', 'string', 'max:200'],
            'items.*.modifier_selections.*.price_adjustment' => ['nullable', 'numeric'],
            'items.*.modifier_selections.*.quantity' => ['nullable', 'integer', 'min:1'],
            // Payments
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,card_mada,card_visa,card_mastercard,mada,apple_pay,stc_pay,store_credit,gift_card,mobile_payment,loyalty_points,bank_transfer,soft_pos'],
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
            // EdfaPay SoftPOS native field names (normalised to card_* by TransactionService)
            'payments.*.approval_code' => ['nullable', 'string'],
            'payments.*.rrn' => ['nullable', 'string'],
            'payments.*.card_scheme' => ['nullable', 'string'],
            'payments.*.masked_card' => ['nullable', 'string'],
            'payments.*.card_transaction_id' => ['nullable', 'string'],
            // Extended EdfaPay card detail fields
            'payments.*.cardholder_name' => ['nullable', 'string', 'max:100'],
            'payments.*.card_expiry' => ['nullable', 'string', 'max:10'],
            'payments.*.stan' => ['nullable', 'string', 'max:20'],
            'payments.*.acquirer_bank' => ['nullable', 'string', 'max:50'],
            'payments.*.application_id' => ['nullable', 'string', 'max:50'],
            'payments.*.sdk_raw_response' => ['nullable', 'array'],
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
