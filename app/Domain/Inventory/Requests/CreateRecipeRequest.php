<?php

namespace App\Domain\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'yield_quantity' => ['sometimes', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],

            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_product_id' => ['required', 'uuid', 'exists:products,id'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
