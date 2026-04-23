<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manager-PIN step-up verification used by POS for elevated actions
 * (large discounts, voids, refunds, tax exemption). Returns a short-lived
 * approval token bound to the requested action + approver_id.
 */
class VerifyManagerPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'min:4', 'max:12'],
            'action' => ['required', 'string', 'in:void,refund,discount,tax_exempt,reopen_session,price_override'],
        ];
    }
}
