<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_name' => $this->organization_name,
            'organization_name_ar' => $this->organization_name_ar,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->owner_email,
            'owner_phone' => $this->owner_phone,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'business_type_id' => $this->business_type_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at instanceof \DateTimeInterface
                ? $this->reviewed_at->toIso8601String()
                : $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
