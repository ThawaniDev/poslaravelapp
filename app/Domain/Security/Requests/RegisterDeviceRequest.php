<?php

namespace App\Domain\Security\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:255'],
            'hardware_id' => ['required', 'string', 'max:255'],
            'os_info' => ['sometimes', 'string', 'max:500'],
            'app_version' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
