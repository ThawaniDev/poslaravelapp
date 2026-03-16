<?php

namespace App\Domain\SystemConfig\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid'],
            'string_key' => ['required', 'string', 'max:200'],
            'locale' => ['required', 'string', 'max:5'],
            'custom_value' => ['required', 'string'],
        ];
    }
}
