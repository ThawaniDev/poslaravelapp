<?php

namespace App\Domain\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/', 'confirmed'],
            'current_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.size' => __('PIN must be exactly 4 digits.'),
            'pin.regex' => __('PIN must contain only digits.'),
            'pin.confirmed' => __('PIN confirmation does not match.'),
        ];
    }
}
