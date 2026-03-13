<?php

namespace App\Domain\Catalog\Requests;

use App\Domain\Catalog\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', Rule::in(array_column(ProductUnit::cases(), 'value'))],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_weighable' => ['sometimes', 'boolean'],
            'tare_weight' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_combo' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],

            'barcodes' => ['sometimes', 'array'],
            'barcodes.*.barcode' => ['required_with:barcodes', 'string', 'max:50'],
            'barcodes.*.is_primary' => ['sometimes', 'boolean'],

            'images' => ['sometimes', 'array'],
            'images.*.image_url' => ['required_with:images', 'string', 'max:500'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sell_price.required' => 'Sell price is required.',
            'sell_price.min' => 'Sell price cannot be negative.',
            'cost_price.min' => 'Cost price cannot be negative.',
        ];
    }
}
