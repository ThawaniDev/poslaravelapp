<?php

namespace App\Domain\Security\Requests;

use App\Domain\Security\Models\SecurityPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PinOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Respect per-store PIN length policy (defaults: min 4, max 8)
        $storeId = $this->input('store_id');
        $minLen = 4;
        $maxLen = 8;

        if ($storeId) {
            $policy = SecurityPolicy::where('store_id', $storeId)->first();
            if ($policy) {
                $minLen = max(4, (int) ($policy->pin_min_length ?? 4));
                $maxLen = min(12, (int) ($policy->pin_max_length ?? 8));
            }
        }

        return [
            'store_id'        => ['required', 'uuid', 'exists:stores,id'],
            'pin'             => [
                'required',
                'string',
                "min:{$minLen}",
                "max:{$maxLen}",
                'regex:/^[0-9]+$/',
            ],
            'permission_code' => ['required', 'string', 'max:125', 'exists:permissions,name'],
            'context'         => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.regex' => __('security.pin_digits_only'),
        ];
    }
}

