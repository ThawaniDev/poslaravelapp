<?php

namespace App\Domain\StaffManagement\Requests;

use App\Domain\StaffManagement\Enums\EmploymentType;
use App\Domain\StaffManagement\Enums\SalaryType;
use App\Domain\StaffManagement\Enums\StaffStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'          => 'required|uuid|exists:stores,id',
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'email'             => 'nullable|email|max:255',
            'phone'             => 'nullable|string|max:20',
            'photo_url'         => 'nullable|string|max:500',
            'national_id'       => 'nullable|string|max:50',
            'pin'               => 'nullable|string|min:4|max:8',
            'nfc_badge_uid'     => 'nullable|string|max:100',
            'biometric_enabled' => 'nullable|boolean',
            'employment_type'   => ['nullable', Rule::enum(EmploymentType::class)],
            'salary_type'       => ['nullable', Rule::enum(SalaryType::class)],
            'hourly_rate'       => 'nullable|numeric|min:0',
            'hire_date'         => 'nullable|date',
            'status'            => ['nullable', Rule::enum(StaffStatus::class)],
            'language_preference' => 'nullable|string|max:5',
        ];
    }
}
