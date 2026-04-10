<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentProviderConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider?->value ?? $this->provider,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'logo_url' => $this->logo_url,
            'is_enabled' => (bool) $this->is_enabled,
            'is_under_maintenance' => (bool) $this->is_under_maintenance,
            'maintenance_message' => $this->maintenance_message,
            'maintenance_message_ar' => $this->maintenance_message_ar,
            'supported_currencies' => $this->supported_currencies,
            'min_amount' => $this->min_amount ? (float) $this->min_amount : null,
            'max_amount' => $this->max_amount ? (float) $this->max_amount : null,
            'supported_installment_counts' => $this->supported_installment_counts,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
