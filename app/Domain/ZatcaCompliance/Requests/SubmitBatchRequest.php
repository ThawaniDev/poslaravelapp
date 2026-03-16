<?php

namespace App\Domain\ZatcaCompliance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoices' => ['required', 'array', 'min:1', 'max:100'],
            'invoices.*.order_id' => ['required', 'uuid'],
            'invoices.*.invoice_number' => ['required', 'string', 'max:50'],
            'invoices.*.invoice_type' => ['required', 'string', 'in:standard,simplified,credit_note,debit_note'],
            'invoices.*.invoice_xml' => ['nullable', 'string'],
            'invoices.*.digital_signature' => ['nullable', 'string'],
            'invoices.*.qr_code_data' => ['nullable', 'string'],
            'invoices.*.total_amount' => ['required', 'numeric', 'min:0'],
            'invoices.*.vat_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
