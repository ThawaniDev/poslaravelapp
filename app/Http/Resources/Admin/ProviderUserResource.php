<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password ?? false,
            'store_id' => $this->store_id,
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
