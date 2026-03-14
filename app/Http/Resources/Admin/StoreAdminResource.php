<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'is_main_branch' => $this->is_main_branch,
            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
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
