<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'name'            => $this->name,
            'display_name'    => $this->display_name,
            'display_name_ar' => $this->display_name_ar,
            'description'     => $this->description,
            'description_ar'  => $this->description_ar,
            'scope'           => $this->scope ?? 'branch',
            'is_predefined'   => (bool) $this->is_predefined,
            'guard_name'      => $this->guard_name ?? 'staff',
            'staff_count'     => $this->when(
                isset($this->resource->staff_count),
                fn () => (int) $this->staff_count,
                fn () => $this->whenLoaded('modelHasRoles', fn () => $this->modelHasRoles->count())
            ),
            'permission_ids'  => $this->whenLoaded('permissions', fn () =>
                $this->permissions->pluck('id')->values()
            ),
            'permissions'     => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
