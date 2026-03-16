<?php

namespace App\Domain\SystemConfig\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:200'],
            'store_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
