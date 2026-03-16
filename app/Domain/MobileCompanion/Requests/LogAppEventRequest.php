<?php

namespace App\Domain\MobileCompanion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogAppEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:100'],
            'event_data' => ['nullable', 'array'],
        ];
    }
}
