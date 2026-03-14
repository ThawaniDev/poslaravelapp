<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'annual_price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*.feature_key' => ['required_with:features', 'string'],
            'features.*.is_enabled' => ['sometimes', 'boolean'],
            'limits' => ['sometimes', 'array'],
            'limits.*.limit_key' => ['required_with:limits', 'string'],
            'limits.*.limit_value' => ['required_with:limits', 'integer', 'min:0'],
            'limits.*.price_per_extra_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
