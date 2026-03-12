<?php

namespace App\Domain\Security\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PinOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'        => ['required', 'uuid', 'exists:stores,id'],
            'pin'             => ['required', 'string', 'size:4', 'regex:/^[0-9]+$/'],
            'permission_code' => ['required', 'string', 'max:125', 'exists:permissions,name'],
            'context'         => ['nullable', 'array'],
        ];
    }
}
