<?php

namespace App\Domain\Inventory\Requests;

use App\Domain\Inventory\Enums\StocktakeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateStocktakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'type' => ['required', new Enum(StocktakeType::class)],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
