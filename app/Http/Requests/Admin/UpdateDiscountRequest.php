<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50'],
            'type' => ['sometimes', 'string', 'in:percentage,fixed'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['sometimes', 'date'],
            'valid_to' => ['sometimes', 'date', 'after:valid_from'],
            'applicable_plan_ids' => ['nullable', 'array'],
            'applicable_plan_ids.*' => ['string', 'exists:subscription_plans,id'],
        ];
    }
}
