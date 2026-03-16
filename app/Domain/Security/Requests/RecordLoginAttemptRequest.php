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
            'ip_address' => ['sometimes', 'string', 'max:45'],
            'device_id' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
