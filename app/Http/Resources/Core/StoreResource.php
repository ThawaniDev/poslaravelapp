<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'manager_id' => $this->manager_id,

            // Basic info
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'slug' => $this->slug,
            'branch_code' => $this->branch_code,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,

            // Location
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'google_maps_url' => $this->google_maps_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Contact
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'email' => $this->email,
            'contact_person' => $this->contact_person,

            // Locale
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'business_type' => $this->business_type?->value,

            // Flags
            'is_active' => $this->is_active,
            'is_main_branch' => $this->is_main_branch,
            'is_warehouse' => $this->is_warehouse,
            'accepts_online_orders' => $this->accepts_online_orders,
            'accepts_reservations' => $this->accepts_reservations,
            'has_delivery' => $this->has_delivery,
            'has_pickup' => $this->has_pickup,

            // Operational
            'opening_date' => $this->opening_date?->toDateString(),
            'closing_date' => $this->closing_date?->toDateString(),
            'max_registers' => $this->max_registers,
            'max_staff' => $this->max_staff,
            'area_sqm' => $this->area_sqm ? (float) $this->area_sqm : null,
            'seating_capacity' => $this->seating_capacity,

            // Legal / licensing
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'municipal_license' => $this->municipal_license,
            'license_expiry_date' => $this->license_expiry_date?->toDateString(),

            // Metadata
            'social_links' => $this->social_links ?? [],
            'extra_metadata' => $this->extra_metadata ?? [],
            'internal_notes' => $this->internal_notes,
            'sort_order' => $this->sort_order,
            'storage_used_mb' => $this->storage_used_mb,

            // Computed
            'staff_count' => $this->whenCounted('users'),
            'register_count' => $this->whenCounted('registers'),

            // Relations
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'name_ar' => $this->organization->name_ar,
                'cr_number' => $this->organization->cr_number,
                'vat_number' => $this->organization->vat_number,
            ]),
            'settings' => $this->whenLoaded('storeSettings', fn () =>
                new StoreSettingsResource($this->storeSettings)
            ),
            'working_hours' => $this->whenLoaded('workingHours', fn () =>
                StoreWorkingHourResource::collection($this->workingHours)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
