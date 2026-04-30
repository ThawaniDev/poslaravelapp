<?php

namespace App\Domain\Security\Requests;

use App\Domain\Security\Enums\LoginAttemptType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RecordLoginAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid'],
            'user_identifier' => ['required', 'string', 'max:255'],
            'attempt_type' => ['required', new Enum(LoginAttemptType::class)],
            'is_successful' => ['required', 'boolean'],
            'ip_address'     => ['sometimes', 'nullable', 'ip'],
            'device_id'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_agent'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'failure_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
