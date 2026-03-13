<?php

namespace App\Domain\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'organization_id'       => $this->organization_id,
            'points_per_sar'        => (float) $this->points_per_sar,
            'sar_per_point'         => (float) $this->sar_per_point,
            'min_redemption_points' => (int) $this->min_redemption_points,
            'points_expiry_months'  => (int) $this->points_expiry_months,
            'excluded_category_ids' => $this->excluded_category_ids,
            'is_active'             => (bool) $this->is_active,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
