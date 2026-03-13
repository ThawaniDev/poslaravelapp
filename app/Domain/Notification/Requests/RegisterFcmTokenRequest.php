<?php

namespace App\Domain\Notification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:500'],
            'device_type' => ['required', 'string', 'in:android,ios'],
        ];
    }
}
