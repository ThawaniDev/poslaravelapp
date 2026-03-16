<?php

namespace App\Domain\SystemConfig\Requests;

use App\Domain\SystemConfig\Enums\TranslationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'string_key' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(TranslationCategory::class)],
            'value_en' => ['required', 'string'],
            'value_ar' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_overridable' => ['nullable', 'boolean'],
        ];
    }
}
