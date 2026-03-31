<?php

namespace App\Domain\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registerId = $this->route('register');

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'device_id'   => ['sometimes', 'required', 'string', 'max:100', Rule::unique('registers', 'device_id')->ignore($registerId)],
            'platform'    => ['sometimes', 'required', 'string', 'in:windows,macos,ios,android'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
