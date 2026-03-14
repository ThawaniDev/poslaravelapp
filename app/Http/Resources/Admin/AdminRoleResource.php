<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug instanceof \BackedEnum ? $this->slug->value : $this->slug,
            'description' => $this->description,
            'is_system' => (bool) $this->is_system,
            'users_count' => $this->admin_user_roles_count ?? 0,
            'permissions_count' => $this->admin_role_permissions_count ?? 0,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];

        if ($this->relationLoaded('adminRolePermissions')) {
            $data['permissions'] = $this->adminRolePermissions->map(function ($rp) {
                $perm = $rp->adminPermission;
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'group' => $perm->group instanceof \BackedEnum ? $perm->group->value : $perm->group,
                    'description' => $perm->description,
                ];
            })->values()->toArray();
        }

        return $data;
    }
}
