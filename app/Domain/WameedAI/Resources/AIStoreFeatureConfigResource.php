<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIStoreFeatureConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'store_id'                 => $this->store_id,
            'ai_feature_definition_id' => $this->ai_feature_definition_id,
            'is_enabled'               => (bool) $this->is_enabled,
            'daily_limit'              => (int) $this->daily_limit,
            'monthly_limit'            => (int) $this->monthly_limit,
            'custom_prompt_override'   => $this->custom_prompt_override,
            'settings_json'            => $this->settings_json,
            'feature_definition'       => $this->whenLoaded('featureDefinition', fn () =>
                new AIFeatureDefinitionResource($this->featureDefinition)
            ),
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
