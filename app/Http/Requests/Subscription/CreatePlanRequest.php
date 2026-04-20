<?php

namespace App\Http\Requests\Subscription;

use App\Domain\Subscription\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:subscription_plans,slug'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_active' => ['sometimes', 'boolean'],
            'is_highlighted' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            // SoftPOS free tier
            'softpos_free_eligible' => ['sometimes', 'boolean'],
            'softpos_free_threshold' => ['nullable', 'integer', 'min:1', 'required_if:softpos_free_eligible,true'],
            'softpos_free_threshold_period' => ['nullable', 'string', 'in:monthly,quarterly,annually', 'required_if:softpos_free_eligible,true'],

            // Feature toggles
            'features' => ['sometimes', 'array'],
            'features.*.feature_key' => ['required_with:features', 'string', 'max:100'],
            'features.*.is_enabled' => ['required_with:features', 'boolean'],

            // Limits
            'limits' => ['sometimes', 'array'],
            'limits.*.limit_key' => ['required_with:limits', 'string', 'max:100'],
            'limits.*.limit_value' => ['required_with:limits', 'integer', 'min:0'],
            'limits.*.price_per_extra_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'A plan with this slug already exists.',
            'monthly_price.min' => 'Monthly price cannot be negative.',
        ];
    }
}
