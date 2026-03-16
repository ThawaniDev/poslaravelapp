<?php

namespace App\Domain\Hardware\Requests;

use App\Domain\Shared\Enums\ConnectionType;
use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class HardwareConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => ['required', 'uuid'],
            'device_type' => ['required', 'string', new Enum(HardwareDeviceType::class)],
            'connection_type' => ['required', 'string', new Enum(ConnectionType::class)],
            'device_name' => ['nullable', 'string', 'max:255'],
            'config_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
