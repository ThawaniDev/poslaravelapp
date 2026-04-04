<?php

namespace App\Domain\Notification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['nullable', 'array'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'sound_enabled' => ['nullable', 'boolean'],
            'email_digest' => ['nullable', 'string', 'in:none,daily,weekly'],
            'per_category_channels' => ['nullable', 'array'],
        ];
    }
}
