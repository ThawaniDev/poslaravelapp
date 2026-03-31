<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required
            'name'                  => ['required', 'string', 'max:255'],

            // Optional basic info
            'name_ar'               => ['nullable', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'description_ar'        => ['nullable', 'string', 'max:2000'],
            'branch_code'           => ['nullable', 'string', 'max:20'],
            'business_type'         => ['nullable', 'string', Rule::in(array_column(BusinessType::cases(), 'value'))],

            // Location
            'address'               => ['nullable', 'string', 'max:500'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'region'                => ['nullable', 'string', 'max:100'],
            'postal_code'           => ['nullable', 'string', 'max:20'],
            'country'               => ['nullable', 'string', 'max:5'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url'       => ['nullable', 'url', 'max:500'],

            // Contact
            'phone'                 => ['nullable', 'string', 'max:20'],
            'secondary_phone'       => ['nullable', 'string', 'max:20'],
            'email'                 => ['nullable', 'email', 'max:255'],
            'contact_person'        => ['nullable', 'string', 'max:255'],

            // Manager
            'manager_id'            => ['nullable', 'uuid', 'exists:users,id'],

            // Locale
            'timezone'              => ['nullable', 'string', 'max:50'],
            'currency'              => ['nullable', 'string', 'max:10'],
            'locale'                => ['nullable', 'string', 'max:10'],

            // Flags
            'is_main_branch'        => ['nullable', 'boolean'],
            'is_warehouse'          => ['nullable', 'boolean'],
            'accepts_online_orders' => ['nullable', 'boolean'],
            'accepts_reservations'  => ['nullable', 'boolean'],
            'has_delivery'          => ['nullable', 'boolean'],
            'has_pickup'            => ['nullable', 'boolean'],

            // Operational
            'opening_date'          => ['nullable', 'date'],
            'closing_date'          => ['nullable', 'date', 'after:opening_date'],
            'max_registers'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_staff'             => ['nullable', 'integer', 'min:1', 'max:500'],
            'area_sqm'              => ['nullable', 'numeric', 'min:0'],
            'seating_capacity'      => ['nullable', 'integer', 'min:0'],

            // Legal / licensing
            'cr_number'             => ['nullable', 'string', 'max:50'],
            'vat_number'            => ['nullable', 'string', 'max:20'],
            'municipal_license'     => ['nullable', 'string', 'max:100'],
            'license_expiry_date'   => ['nullable', 'date'],

            // Media
            'logo_url'              => ['nullable', 'url', 'max:500'],
            'cover_image_url'       => ['nullable', 'url', 'max:500'],

            // Metadata
            'social_links'          => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.twitter'  => ['nullable', 'string', 'max:255'],
            'social_links.facebook' => ['nullable', 'string', 'max:255'],
            'social_links.website'  => ['nullable', 'url', 'max:255'],
            'social_links.tiktok'   => ['nullable', 'string', 'max:255'],
            'social_links.snapchat' => ['nullable', 'string', 'max:255'],
            'extra_metadata'        => ['nullable', 'array'],
            'internal_notes'        => ['nullable', 'string', 'max:5000'],
            'sort_order'            => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => __('validation.required', ['attribute' => __('Branch name')]),
            'closing_date.after'       => __('Closing date must be after opening date.'),
            'business_type.in'         => __('Invalid business type.'),
            'manager_id.exists'        => __('Selected manager does not exist.'),
        ];
    }
}
