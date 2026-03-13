<?php

namespace App\Domain\Catalog\Requests;

use App\Domain\Catalog\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description_ar' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sell_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', Rule::in(array_column(ProductUnit::cases(), 'value'))],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'is_weighable' => ['sometimes', 'boolean'],
            'tare_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_combo' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],

            'barcodes' => ['sometimes', 'array'],
            'barcodes.*.barcode' => ['required_with:barcodes', 'string', 'max:50'],
            'barcodes.*.is_primary' => ['sometimes', 'boolean'],

            'images' => ['sometimes', 'array'],
            'images.*.image_url' => ['required_with:images', 'string', 'max:500'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
