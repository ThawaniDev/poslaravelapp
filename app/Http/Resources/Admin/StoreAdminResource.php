<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'business_type' => $this->business_type instanceof \BackedEnum ? $this->business_type->value : $this->business_type,
            'currency'      => $this->currency,
            'is_active'     => $this->is_active,
            'is_main_branch' => $this->is_main_branch,
            'suspend_reason' => $this->suspend_reason,
            'suspended_at'  => $this->suspended_at instanceof \DateTimeInterface
                ? $this->suspended_at->toIso8601String()
                : $this->suspended_at,
            'city'          => $this->city,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'cr_number'     => $this->cr_number,
            'vat_number'    => $this->vat_number,
            'organization'  => $this->whenLoaded('organization', function () {
                return [
                    'id'         => $this->organization->id,
                    'name'       => $this->organization->name,
                    'cr_number'  => $this->organization->cr_number,
                    'vat_number' => $this->organization->vat_number,
                ];
            }),
            'active_subscription' => $this->whenLoaded('organization', function () {
                $sub = $this->organization?->subscription ?? null;
                if (!$sub) return null;
                return [
                    'plan_name'           => $sub->subscriptionPlan?->name,
                    'status'              => $sub->status instanceof \BackedEnum ? $sub->status->value : $sub->status,
                    'billing_cycle'       => $sub->billing_cycle instanceof \BackedEnum ? $sub->billing_cycle->value : $sub->billing_cycle,
                    'current_period_end'  => $sub->current_period_end?->toIso8601String(),
                ];
            }),
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
