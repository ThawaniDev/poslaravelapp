<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InviteAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:admin_users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:admin_roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
