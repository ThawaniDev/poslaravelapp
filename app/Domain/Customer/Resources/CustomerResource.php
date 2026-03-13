<?php

namespace App\Domain\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'organization_id'         => $this->organization_id,
            'name'                    => $this->name,
            'phone'                   => $this->phone,
            'email'                   => $this->email,
            'address'                 => $this->address,
            'date_of_birth'           => $this->date_of_birth?->toDateString(),
            'loyalty_code'            => $this->loyalty_code,
            'loyalty_points'          => (int) $this->loyalty_points,
            'store_credit_balance'    => (float) $this->store_credit_balance,
            'group_id'                => $this->group_id,
            'tax_registration_number' => $this->tax_registration_number,
            'notes'                   => $this->notes,
            'total_spend'             => (float) $this->total_spend,
            'visit_count'             => (int) $this->visit_count,
            'last_visit_at'           => $this->last_visit_at?->toIso8601String(),
            'sync_version'            => $this->sync_version,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
