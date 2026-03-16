<?php

namespace App\Domain\SystemConfig\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.string_key' => ['required', 'string', 'max:200'],
            'translations.*.category' => ['required', 'string', 'max:30'],
            'translations.*.value_en' => ['required', 'string'],
            'translations.*.value_ar' => ['required', 'string'],
            'translations.*.description' => ['nullable', 'string', 'max:255'],
            'translations.*.is_overridable' => ['nullable', 'boolean'],
        ];
    }
}
