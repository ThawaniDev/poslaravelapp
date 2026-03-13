<?php

namespace App\Domain\StaffManagement\Requests;

use App\Domain\StaffManagement\Enums\EmploymentType;
use App\Domain\StaffManagement\Enums\SalaryType;
use App\Domain\StaffManagement\Enums\StaffStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'        => 'sometimes|string|max:100',
            'last_name'         => 'sometimes|string|max:100',
            'email'             => 'sometimes|nullable|email|max:255',
            'phone'             => 'sometimes|nullable|string|max:20',
            'photo_url'         => 'sometimes|nullable|string|max:500',
            'national_id'       => 'sometimes|nullable|string|max:50',
            'nfc_badge_uid'     => 'sometimes|nullable|string|max:100',
            'biometric_enabled' => 'sometimes|boolean',
            'employment_type'   => ['sometimes', Rule::enum(EmploymentType::class)],
            'salary_type'       => ['sometimes', Rule::enum(SalaryType::class)],
            'hourly_rate'       => 'sometimes|nullable|numeric|min:0',
            'hire_date'         => 'sometimes|nullable|date',
            'termination_date'  => 'sometimes|nullable|date',
            'status'            => ['sometimes', Rule::enum(StaffStatus::class)],
            'language_preference' => 'sometimes|nullable|string|max:5',
        ];
    }
}
