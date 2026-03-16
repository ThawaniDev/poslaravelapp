<?php

namespace App\Domain\Hardware\Requests;

use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class EventLogRequest extends FormRequest
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
            'event' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'array'],
        ];
    }
}
