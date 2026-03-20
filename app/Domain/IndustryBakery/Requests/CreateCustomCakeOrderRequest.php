<?php

namespace App\Domain\IndustryBakery\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomCakeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => ['nullable', 'uuid'],
            'description'      => ['required', 'string', 'max:1000'],
            'size'             => ['required', 'string', 'max:50'],
            'flavor'           => ['required', 'string', 'max:100'],
            'decoration_notes' => ['nullable', 'string', 'max:2000'],
            'delivery_date'    => ['required', 'date', 'after:today'],
            'delivery_time'    => ['nullable', 'string', 'max:10'],
            'price'            => ['required', 'numeric', 'min:0'],
            'deposit_paid'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
