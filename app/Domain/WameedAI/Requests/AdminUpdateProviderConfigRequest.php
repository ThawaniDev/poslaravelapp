<?php

namespace App\Domain\WameedAI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateProviderConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider'               => ['required', 'string', 'in:openai,anthropic,google'],
            'default_model'          => ['required', 'string', 'max:100'],
            'api_key_encrypted'      => ['required', 'string', 'max:500'],
            'max_tokens_per_request' => ['nullable', 'integer', 'min:1', 'max:32768'],
            'is_active'              => ['nullable', 'boolean'],
        ];
    }
}
