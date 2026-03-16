<?php

namespace App\Domain\MobileCompanion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
            'language' => ['nullable', 'string', 'in:en,ar'],
            'compact_mode' => ['nullable', 'boolean'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'biometric_lock' => ['nullable', 'boolean'],
            'default_page' => ['nullable', 'string', 'max:50'],
            'currency_display' => ['nullable', 'string', 'in:symbol,code'],
        ];
    }
}
