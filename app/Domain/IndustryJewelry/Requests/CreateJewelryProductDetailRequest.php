<?php

namespace App\Domain\IndustryJewelry\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateJewelryProductDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'           => ['required', 'uuid'],
            'metal_type'           => ['required', 'string', 'in:gold,silver,platinum'],
            'karat'                => ['required', 'string', 'max:10'],
            'gross_weight_g'       => ['required', 'numeric', 'min:0'],
            'net_weight_g'         => ['required', 'numeric', 'min:0'],
            'making_charges_type'  => ['required', 'string', 'in:flat,percentage,per_gram'],
            'making_charges_value' => ['required', 'numeric', 'min:0'],
            'stone_type'           => ['nullable', 'string', 'max:100'],
            'stone_weight_carat'   => ['nullable', 'numeric', 'min:0'],
            'stone_count'          => ['nullable', 'integer', 'min:0'],
            'certificate_number'   => ['nullable', 'string', 'max:100'],
            'certificate_url'      => ['nullable', 'url', 'max:500'],
        ];
    }
}
