<?php

namespace App\Domain\StaffManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'          => 'required|uuid|exists:stores,id',
            'staff_user_id'     => 'required|uuid|exists:staff_users,id',
            'shift_template_id' => 'nullable|uuid|exists:shift_templates,id',
            'date'              => 'required|date',
            'status'            => 'nullable|string|max:20',
        ];
    }
}
