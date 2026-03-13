<?php

namespace App\Domain\StaffManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => 'required|uuid|exists:staff_users,id',
            'store_id'      => 'required|uuid|exists:stores,id',
            'action'        => 'required|in:clock_in,clock_out,start_break,end_break',
            'notes'         => 'nullable|string|max:500',
            'attendance_record_id' => 'required_if:action,start_break,end_break|nullable|uuid',
        ];
    }
}
