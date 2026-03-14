<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:admin_roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
