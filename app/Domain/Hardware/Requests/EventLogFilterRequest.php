<?php

namespace App\Domain\Hardware\Requests;

use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class EventLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => ['nullable', 'uuid'],
            'device_type' => ['nullable', 'string', new Enum(HardwareDeviceType::class)],
            'event' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
