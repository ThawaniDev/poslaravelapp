<?php

namespace App\Domain\PredefinedCatalog\Requests;

use App\Domain\Catalog\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePredefinedProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_type_id' => ['sometimes', 'uuid', 'exists:business_types,id'],
            'predefined_category_id' => ['nullable', 'uuid', 'exists:predefined_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'sell_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', Rule::in(array_column(ProductUnit::cases(), 'value'))],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_weighable' => ['sometimes', 'boolean'],
            'tare_weight' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'images' => ['sometimes', 'array'],
            'images.*.image_url' => ['required_with:images', 'string', 'max:500'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
