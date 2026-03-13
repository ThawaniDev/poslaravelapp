<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'staff_user_id'       => $this->staff_user_id,
            'type'                => $this->type?->value,
            'percentage'          => $this->percentage ? (float) $this->percentage : null,
            'tiers_json'          => $this->tiers_json,
            'product_category_id' => $this->product_category_id,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
