<?php

namespace App\Domain\IndustryElectronics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterImeiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'              => ['required', 'uuid'],
            'imei'                    => ['required', 'string', 'max:20'],
            'imei2'                   => ['nullable', 'string', 'max:20'],
            'serial_number'           => ['nullable', 'string', 'max:100'],
            'condition_grade'         => ['nullable', 'string', 'in:new,like_new,good,fair,poor,A,B,C,D'],
            'purchase_price'          => ['nullable', 'numeric', 'min:0'],
            'warranty_end_date'       => ['nullable', 'date'],
            'store_warranty_end_date' => ['nullable', 'date'],
        ];
    }
}
