<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'two_factor_enabled' => $this->two_factor_enabled,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->toIso8601String(),
            'roles' => $this->whenLoaded('adminUserRoles', function () {
                return $this->adminUserRoles->map(fn ($aur) => [
                    'role_id' => $aur->admin_role_id,
                    'role_name' => $aur->adminRole?->name ?? null,
                    'role_slug' => $aur->adminRole?->slug instanceof \BackedEnum
                        ? $aur->adminRole->slug->value
                        : ($aur->adminRole?->slug ?? null),
                    'assigned_at' => $aur->assigned_at,
                ]);
            }),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
