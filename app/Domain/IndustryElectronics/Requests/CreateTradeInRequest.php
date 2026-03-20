<?php

namespace App\Domain\IndustryElectronics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTradeInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'          => ['nullable', 'uuid'],
            'device_description'   => ['required', 'string', 'max:500'],
            'imei'                 => ['nullable', 'string', 'max:20'],
            'condition_grade'      => ['required', 'string', 'in:new,like_new,good,fair,poor,A,B,C,D'],
            'assessed_value'       => ['required', 'numeric', 'min:0'],
            'applied_to_order_id'  => ['nullable', 'uuid'],
            'staff_user_id'        => ['nullable', 'uuid'],
        ];
    }
}
