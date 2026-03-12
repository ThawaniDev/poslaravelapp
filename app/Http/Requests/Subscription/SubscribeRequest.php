<?php

namespace App\Http\Requests\Subscription;

use App\Domain\Subscription\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['sometimes', Rule::enum(BillingCycle::class)],
            'payment_method' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected subscription plan does not exist.',
            'billing_cycle.Illuminate\Validation\Rules\Enum' => 'Invalid billing cycle. Must be monthly or yearly.',
        ];
    }
}
