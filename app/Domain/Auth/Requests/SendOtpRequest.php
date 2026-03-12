<?php

namespace App\Domain\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'exists:users,email'],
            'purpose' => ['sometimes', 'string', 'in:login,password_reset,email_verify'],
            'channel' => ['sometimes', 'string', 'in:sms,email,whatsapp'],
        ];
    }
}
