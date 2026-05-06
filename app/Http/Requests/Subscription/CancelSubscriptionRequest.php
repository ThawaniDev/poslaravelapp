<?php

namespace App\Http\Requests\Subscription;

use App\Domain\ProviderRegistration\Models\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'reason_category' => ['nullable', 'string', Rule::in(CancellationReason::CATEGORIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_category.in' => 'Invalid cancellation reason. Must be one of: ' . implode(', ', CancellationReason::CATEGORIES) . '.',
        ];
    }
}
