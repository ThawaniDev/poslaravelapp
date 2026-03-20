<?php

namespace App\Domain\IndustryElectronics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImeiRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_grade'         => ['sometimes', 'string', 'in:new,like_new,good,fair,poor,A,B,C,D'],
            'status'                  => ['sometimes', 'string', 'in:in_stock,sold,traded_in,returned'],
            'sold_order_id'           => ['nullable', 'uuid'],
            'warranty_end_date'       => ['nullable', 'date'],
            'store_warranty_end_date' => ['nullable', 'date'],
        ];
    }
}
