<?php

namespace App\Domain\StaffManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'store_id'            => $this->store_id,
            'user_id'             => $this->user_id,
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'photo_url'           => $this->photo_url,
            'national_id'         => $this->national_id,
            'nfc_badge_uid'       => $this->nfc_badge_uid,
            'biometric_enabled'   => (bool) $this->biometric_enabled,
            'employment_type'     => $this->employment_type?->value,
            'salary_type'         => $this->salary_type?->value,
            'hourly_rate'         => $this->hourly_rate ? (float) $this->hourly_rate : null,
            'hire_date'           => $this->hire_date?->toDateString(),
            'termination_date'    => $this->termination_date?->toDateString(),
            'status'              => $this->status?->value,
            'language_preference' => $this->language_preference,
            'branch_assignments'  => StaffBranchAssignmentResource::collection($this->whenLoaded('staffBranchAssignments')),
            'commission_rules'    => CommissionRuleResource::collection($this->whenLoaded('commissionRules')),
            'linked_user'         => $this->whenLoaded('user', fn () => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
