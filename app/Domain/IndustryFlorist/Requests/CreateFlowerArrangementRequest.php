<?php

namespace App\Domain\IndustryFlorist\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFlowerArrangementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'occasion'    => ['nullable', 'string', 'max:100'],
            'items_json'  => ['required', 'array'],
            'items_json.*.product_id' => ['required', 'uuid'],
            'items_json.*.quantity'   => ['required', 'integer', 'min:1'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'is_template' => ['boolean'],
        ];
    }
}
