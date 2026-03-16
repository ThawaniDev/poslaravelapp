<?php

namespace App\Domain\PosCustomization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuickAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grid_rows' => 'sometimes|integer|min:1|max:6',
            'grid_cols' => 'sometimes|integer|min:1|max:8',
            'buttons_json' => 'sometimes|array',
            'buttons_json.*.id' => 'required_with:buttons_json|string',
            'buttons_json.*.label' => 'required_with:buttons_json|string|max:50',
            'buttons_json.*.product_id' => 'sometimes|nullable|string',
            'buttons_json.*.color' => 'sometimes|nullable|string|max:9',
            'buttons_json.*.icon' => 'sometimes|nullable|string|max:50',
            'buttons_json.*.row' => 'sometimes|integer|min:0',
            'buttons_json.*.col' => 'sometimes|integer|min:0',
        ];
    }
}
