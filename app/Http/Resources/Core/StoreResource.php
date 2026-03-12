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
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'branch_code' => $this->branch_code,
            'address' => $this->address,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'business_type' => $this->business_type?->value,
            'is_active' => $this->is_active,
            'is_main_branch' => $this->is_main_branch,
            'storage_used_mb' => $this->storage_used_mb,
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
