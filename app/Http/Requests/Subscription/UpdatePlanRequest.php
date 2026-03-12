<?php

namespace App\Http\Requests\Subscription;

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
            'name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', 'unique:subscription_plans,slug,' . $this->route('planId')],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'annual_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:90'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            'features' => ['sometimes', 'array'],
            'features.*.feature_key' => ['required_with:features', 'string', 'max:100'],
            'features.*.is_enabled' => ['required_with:features', 'boolean'],

            'limits' => ['sometimes', 'array'],
            'limits.*.limit_key' => ['required_with:limits', 'string', 'max:100'],
            'limits.*.limit_value' => ['required_with:limits', 'integer', 'min:0'],
            'limits.*.price_per_extra_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
