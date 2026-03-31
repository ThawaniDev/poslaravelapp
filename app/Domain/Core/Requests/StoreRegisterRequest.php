<?php

namespace App\Domain\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'device_id'   => ['required', 'string', 'max:100', 'unique:registers,device_id'],
            'platform'    => ['required', 'string', 'in:windows,macos,ios,android'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
