<?php

namespace App\Domain\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role?->value,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'name_ar' => $this->store->name_ar,
                'slug' => $this->store->slug,
                'currency' => $this->store->currency,
                'locale' => $this->store->locale,
                'business_type' => $this->store->business_type,
                'is_main_branch' => $this->store->is_main_branch,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'name_ar' => $this->organization->name_ar,
                'slug' => $this->organization->slug,
                'country' => $this->organization->country,
            ]),
            'permissions' => $this->whenLoaded('permissions', fn () =>
                $this->getAllPermissions()->pluck('name')->toArray()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
