<?php

namespace App\Domain\IndustryJewelry\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJewelryProductDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_weight_g'       => ['sometimes', 'numeric', 'min:0'],
            'net_weight_g'         => ['sometimes', 'numeric', 'min:0'],
            'making_charges_type'  => ['sometimes', 'string', 'in:flat,percentage,per_gram'],
            'making_charges_value' => ['sometimes', 'numeric', 'min:0'],
            'stone_type'           => ['nullable', 'string', 'max:100'],
            'stone_weight_carat'   => ['nullable', 'numeric', 'min:0'],
            'stone_count'          => ['nullable', 'integer', 'min:0'],
            'certificate_number'   => ['nullable', 'string', 'max:100'],
            'certificate_url'      => ['nullable', 'url', 'max:500'],
        ];
    }
}
