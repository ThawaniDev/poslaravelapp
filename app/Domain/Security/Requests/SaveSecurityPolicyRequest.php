<?php

namespace App\Domain\Security\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin_min_length' => ['sometimes', 'integer', 'min:4', 'max:8'],
            'pin_max_length' => ['sometimes', 'integer', 'min:4', 'max:12'],
            'auto_lock_seconds' => ['sometimes', 'integer', 'min:30', 'max:3600'],
            'max_failed_attempts' => ['sometimes', 'integer', 'min:3', 'max:20'],
            'lockout_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'require_2fa_owner' => ['sometimes', 'boolean'],
            'session_max_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],
            'require_pin_override_void' => ['sometimes', 'boolean'],
            'require_pin_override_return' => ['sometimes', 'boolean'],
            'require_pin_override_discount' => ['sometimes', 'boolean'],
            'discount_override_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
