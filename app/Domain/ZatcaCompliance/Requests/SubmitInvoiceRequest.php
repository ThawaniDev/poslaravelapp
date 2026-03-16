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
        ];
    }
}
