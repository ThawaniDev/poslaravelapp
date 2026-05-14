<?php

namespace App\Http\Requests\Subscription;

use App\Domain\Subscription\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('subscription_plans', 'id')->where('is_active', true),
            ],
            'billing_cycle' => ['sometimes', Rule::enum(BillingCycle::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected subscription plan does not exist or is no longer available.',
        ];
    }
}
