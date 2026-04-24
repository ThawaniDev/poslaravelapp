<?php

namespace App\Domain\ZatcaCompliance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid'],
            'invoice_number' => ['required', 'string', 'max:50'],
            'invoice_type' => ['required', 'string', 'in:standard,simplified,credit_note,debit_note'],
            'invoice_xml' => ['nullable', 'string'],
            'digital_signature' => ['nullable', 'string'],
            'qr_code_data' => ['nullable', 'string'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'vat_amount' => ['required', 'numeric', 'min:0'],

            // Phase 2 optional inputs
            'is_b2b' => ['sometimes', 'boolean'],
            'buyer_name' => ['sometimes', 'string', 'max:255'],
            'buyer_tax_number' => ['sometimes', 'string', 'max:50'],
            'customer_id' => ['sometimes', 'uuid'],
            'reference_invoice_uuid' => [
                'required_if:invoice_type,credit_note',
                'required_if:invoice_type,debit_note',
                'nullable', 'string', 'max:64',
            ],
            'adjustment_reason' => [
                'required_if:invoice_type,debit_note',
                'nullable', 'string', 'max:255',
            ],
            'lines' => ['sometimes', 'array'],
            'lines.*.name' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.tax_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
