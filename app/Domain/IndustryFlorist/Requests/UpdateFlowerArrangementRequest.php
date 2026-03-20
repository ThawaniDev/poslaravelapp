<?php

namespace App\Domain\IndustryFlorist\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlowerArrangementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'occasion'    => ['nullable', 'string', 'max:100'],
            'items_json'  => ['sometimes', 'array'],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'is_template' => ['sometimes', 'boolean'],
        ];
    }
}
