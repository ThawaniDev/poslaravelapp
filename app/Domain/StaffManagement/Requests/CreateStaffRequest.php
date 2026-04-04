<?php

namespace App\Domain\StaffManagement\Requests;

use App\Domain\Auth\Enums\UserRole;
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
        $rules = [
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

            // User account creation (optional)
            'create_user_account' => 'nullable|boolean',
            'password'            => 'nullable|string|min:8|max:128',
            'user_role'           => ['nullable', Rule::enum(UserRole::class)],
        ];

        // When creating a user account, email and password are required
        if ($this->boolean('create_user_account')) {
            $rules['email'] = 'required|email|max:255|unique:users,email';
            $rules['password'] = 'required|string|min:8|max:128';
            $rules['user_role'] = ['required', Rule::enum(UserRole::class)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user account with this email already exists.',
            'email.required' => 'Email is required when creating a user account.',
            'password.required' => 'Password is required when creating a user account.',
            'user_role.required' => 'User role is required when creating a user account.',
        ];
    }
}
