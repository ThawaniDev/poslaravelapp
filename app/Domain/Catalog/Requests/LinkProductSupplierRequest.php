<?php

namespace App\Domain\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkProductSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers' => ['required', 'array', 'min:1'],
            'suppliers.*.supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'suppliers.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'suppliers.*.supplier_sku' => ['nullable', 'string', 'max:100'],
        ];
    }
}
