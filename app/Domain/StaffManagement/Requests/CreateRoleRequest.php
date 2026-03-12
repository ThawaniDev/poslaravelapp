<?php

namespace App\Domain\StaffManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Middleware handles auth; policy checked in controller
    }

    public function rules(): array
    {
        return [
            'store_id'        => ['required', 'uuid', 'exists:stores,id'],
            'name'            => ['required', 'string', 'max:125'],
            'display_name'    => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:500'],
            'permission_ids'  => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
