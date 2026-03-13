<?php

namespace App\Domain\AccountingIntegration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'provider' => $this->provider->value,
            'company_name' => $this->company_name,
            'realm_id' => $this->realm_id,
            'tenant_id' => $this->tenant_id,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
        ];
    }
}
