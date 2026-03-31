<?php

namespace App\Domain\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.store_id' => ['required', 'uuid', 'exists:stores,id'],
            'prices.*.sell_price' => ['required', 'numeric', 'min:0'],
            'prices.*.valid_from' => ['nullable', 'date'],
            'prices.*.valid_to' => ['nullable', 'date', 'after:prices.*.valid_from'],
        ];
    }
}
