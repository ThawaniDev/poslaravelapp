<?php

namespace App\Domain\AccountingIntegration\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountMappingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'store_id'              => $this->store_id,
            'pos_account_key'       => $this->pos_account_key,
            'provider_account_id'   => $this->provider_account_id,
            'provider_account_name' => $this->provider_account_name,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
