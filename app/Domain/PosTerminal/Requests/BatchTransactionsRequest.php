<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch upload of transactions captured offline by a register. Each entry
 * carries its own client-assigned `transaction_number` (register-prefixed)
 * + `client_uuid` so the server can deduplicate idempotently.
 */
class BatchTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => ['required', 'string'],
            'transactions' => ['required', 'array', 'min:1', 'max:200'],
            'transactions.*.client_uuid' => ['required', 'string', 'max:64'],
            'transactions.*.transaction_number' => ['required', 'string', 'max:60'],
            'transactions.*.type' => ['required', 'string', 'in:sale,return,exchange'],
            'transactions.*.pos_session_id' => ['nullable', 'string'],
            'transactions.*.customer_id' => ['nullable', 'string'],
            'transactions.*.subtotal' => ['required', 'numeric', 'min:0'],
            'transactions.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.tip_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.total_amount' => ['required', 'numeric', 'min:0'],
            'transactions.*.is_tax_exempt' => ['nullable', 'boolean'],
            'transactions.*.return_transaction_id' => ['nullable', 'string'],
            'transactions.*.notes' => ['nullable', 'string'],
            'transactions.*.created_at' => ['nullable', 'date'],
            'transactions.*.items' => ['required', 'array', 'min:1'],
            'transactions.*.items.*.product_id' => ['nullable', 'string'],
            'transactions.*.items.*.barcode' => ['nullable', 'string'],
            'transactions.*.items.*.product_name' => ['required', 'string'],
            'transactions.*.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'transactions.*.items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.line_total' => ['required', 'numeric'],
            'transactions.*.items.*.age_verified' => ['nullable', 'boolean'],
            'transactions.*.payments' => ['required', 'array', 'min:1'],
            'transactions.*.payments.*.method' => ['required', 'string'],
            'transactions.*.payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.tax_exemption' => ['nullable', 'array'],
            'transactions.*.tax_exemption.exemption_type' => ['required_with:transactions.*.tax_exemption', 'string'],
            'transactions.*.tax_exemption.customer_tax_id' => ['nullable', 'string', 'max:50'],
            'transactions.*.tax_exemption.certificate_number' => ['nullable', 'string', 'max:100'],
            'transactions.*.tax_exemption.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
