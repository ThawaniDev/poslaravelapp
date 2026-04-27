<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'display_name'    => $this->display_name,
            'display_name_ar' => $this->display_name_ar,
            'description'     => $this->description,
            'description_ar'  => $this->description_ar,
            'module'          => $this->module,
            'guard_name'      => $this->guard_name ?? 'staff',
            'requires_pin'    => (bool) $this->requires_pin,
            'sort_order'      => (int) ($this->sort_order ?? 0),
        ];
    }
}
