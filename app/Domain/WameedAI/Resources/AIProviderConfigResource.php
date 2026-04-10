<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIProviderConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'provider'               => $this->provider,
            'default_model'          => $this->default_model,
            'max_tokens_per_request' => (int) $this->max_tokens_per_request,
            'is_active'              => (bool) $this->is_active,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
