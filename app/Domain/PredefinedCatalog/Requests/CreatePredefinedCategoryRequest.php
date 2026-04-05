<?php

namespace App\Domain\PredefinedCatalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePredefinedCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_type_id' => ['required', 'uuid', 'exists:business_types,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:predefined_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
