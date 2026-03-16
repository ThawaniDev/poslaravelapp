<?php

namespace App\Domain\SystemConfig\Requests;

use App\Domain\SystemConfig\Enums\CalendarSystem;
use App\Domain\SystemConfig\Enums\LocaleDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale_code' => ['required', 'string', 'max:10'],
            'language_name' => ['required', 'string', 'max:50'],
            'language_name_native' => ['required', 'string', 'max:50'],
            'direction' => ['required', Rule::enum(LocaleDirection::class)],
            'date_format' => ['nullable', 'string', 'max:20'],
            'number_format' => ['nullable', 'string', 'max:20'],
            'calendar_system' => ['nullable', Rule::enum(CalendarSystem::class)],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
