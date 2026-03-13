<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffBranchAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'staff_user_id' => $this->staff_user_id,
            'branch_id'     => $this->branch_id,
            'role_id'       => $this->role_id,
            'is_primary'    => (bool) $this->is_primary,
        ];
    }
}
