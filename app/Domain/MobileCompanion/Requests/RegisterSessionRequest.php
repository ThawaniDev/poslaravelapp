<?php

namespace App\Domain\MobileCompanion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:100'],
            'device_os' => ['required', 'string', 'max:50'],
            'app_version' => ['required', 'string', 'max:20'],
        ];
    }
}
