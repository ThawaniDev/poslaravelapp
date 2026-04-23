<?php

namespace App\Domain\PosTerminal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyInventoryAdjustmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustments' => ['required', 'array', 'min:1', 'max:200'],
            'adjustments.*.product_id' => ['required', 'string'],
            // 'in' = stock added back (e.g. opening, count up), 'out' = stock removed
            'adjustments.*.direction' => ['required', 'string', 'in:in,out'],
            'adjustments.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'adjustments.*.reason' => ['required', 'string', 'max:200'],
            'adjustments.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'adjustments.*.client_uuid' => ['nullable', 'string', 'max:64'],
        ];
    }
}
