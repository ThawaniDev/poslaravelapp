<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'is_enabled'             => ['required', 'boolean'],
            'daily_limit'            => ['nullable', 'integer', 'min:0', 'max:100000'],
            'monthly_limit'          => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'custom_prompt_override' => ['nullable', 'string', 'max:5000'],
            'settings_json'          => ['nullable', 'array'],
        ];
    }
}
