<?php

namespace App\Domain\Hardware\Requests;

use App\Domain\Shared\Enums\ConnectionType;
use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TestDeviceRequest extends FormRequest
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
            'test_type' => ['nullable', 'string', 'in:connection,print,scan,weigh,open_drawer'],
        ];
    }
}
