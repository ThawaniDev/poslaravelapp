<?php

namespace App\Domain\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'organization_id'  => $this->organization_id,
            'name'             => $this->name,
            'discount_percent' => (float) $this->discount_percent,
        ];
    }
}
