<?php

namespace App\Domain\IndustryBakery\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomCakeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decoration_notes'    => ['nullable', 'string', 'max:2000'],
            'delivery_date'       => ['sometimes', 'date'],
            'delivery_time'       => ['nullable', 'string', 'max:10'],
            'price'               => ['sometimes', 'numeric', 'min:0'],
            'deposit_paid'        => ['nullable', 'numeric', 'min:0'],
            'reference_image_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
