<?php

namespace App\Domain\StaffManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['sometimes', 'string', 'max:125'],
            'display_name'    => ['sometimes', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:500'],
            'permission_ids'  => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
