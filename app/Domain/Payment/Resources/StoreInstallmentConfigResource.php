<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreInstallmentConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'is_enabled' => (bool) $this->is_enabled,
            'environment' => $this->environment,
            'is_fully_configured' => $this->isFullyConfigured(),
            'is_available' => $this->isAvailable(),
            'masked_credentials' => $this->getMaskedCredentials(),
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'failure_url' => $this->failure_url,
            'provider_config' => new InstallmentProviderConfigResource($this->whenLoaded('providerConfig')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
