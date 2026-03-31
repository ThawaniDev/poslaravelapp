<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'name'                  => ['sometimes', 'string', 'max:255'],
            'name_ar'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description_ar'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'branch_code'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'business_type'         => ['sometimes', 'nullable', 'string', Rule::in(array_column(BusinessType::cases(), 'value'))],

            // Location
            'address'               => ['sometimes', 'nullable', 'string', 'max:500'],
            'city'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'region'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'country'               => ['sometimes', 'nullable', 'string', 'max:5'],
            'latitude'              => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'google_maps_url'       => ['sometimes', 'nullable', 'url', 'max:500'],

            // Contact
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:20'],
            'secondary_phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'                 => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_person'        => ['sometimes', 'nullable', 'string', 'max:255'],

            // Manager
            'manager_id'            => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],

            // Locale
            'timezone'              => ['sometimes', 'string', 'max:50'],
            'currency'              => ['sometimes', 'string', 'max:10'],
            'locale'                => ['sometimes', 'string', 'max:10'],

            // Flags
            'is_active'             => ['sometimes', 'boolean'],
            'is_main_branch'        => ['sometimes', 'boolean'],
            'is_warehouse'          => ['sometimes', 'boolean'],
            'accepts_online_orders' => ['sometimes', 'boolean'],
            'accepts_reservations'  => ['sometimes', 'boolean'],
            'has_delivery'          => ['sometimes', 'boolean'],
            'has_pickup'            => ['sometimes', 'boolean'],

            // Operational
            'opening_date'          => ['sometimes', 'nullable', 'date'],
            'closing_date'          => ['sometimes', 'nullable', 'date', 'after:opening_date'],
            'max_registers'         => ['sometimes', 'integer', 'min:1', 'max:100'],
            'max_staff'             => ['sometimes', 'integer', 'min:1', 'max:500'],
            'area_sqm'              => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'seating_capacity'      => ['sometimes', 'nullable', 'integer', 'min:0'],

            // Legal / licensing
            'cr_number'             => ['sometimes', 'nullable', 'string', 'max:50'],
            'vat_number'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'municipal_license'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'license_expiry_date'   => ['sometimes', 'nullable', 'date'],

            // Media
            'logo_url'              => ['sometimes', 'nullable', 'url', 'max:500'],
            'cover_image_url'       => ['sometimes', 'nullable', 'url', 'max:500'],

            // Metadata
            'social_links'          => ['sometimes', 'nullable', 'array'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.twitter'  => ['nullable', 'string', 'max:255'],
            'social_links.facebook' => ['nullable', 'string', 'max:255'],
            'social_links.website'  => ['nullable', 'url', 'max:255'],
            'social_links.tiktok'   => ['nullable', 'string', 'max:255'],
            'social_links.snapchat' => ['nullable', 'string', 'max:255'],
            'extra_metadata'        => ['sometimes', 'nullable', 'array'],
            'internal_notes'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order'            => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'closing_date.after' => __('Closing date must be after opening date.'),
            'business_type.in'   => __('Invalid business type.'),
            'manager_id.exists'  => __('Selected manager does not exist.'),
        ];
    }
}
