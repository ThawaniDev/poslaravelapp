<?php

namespace App\Domain\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkProductActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['required', 'uuid', 'exists:products,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,delete,change_category'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id', 'required_if:action,change_category'],
        ];
    }
}
