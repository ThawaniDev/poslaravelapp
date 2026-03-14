<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTeamUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'last_login_at' => $this->last_login_at instanceof \DateTimeInterface
                ? $this->last_login_at->toIso8601String()
                : $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
        ];

        if ($this->relationLoaded('adminUserRoles')) {
            $data['roles'] = $this->adminUserRoles->map(function ($ur) {
                $role = $ur->adminRole;
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug instanceof \BackedEnum ? $role->slug->value : $role->slug,
                ];
            })->values()->toArray();
        }

        return $data;
    }
}
