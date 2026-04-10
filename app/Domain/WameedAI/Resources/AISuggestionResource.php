<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AISuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'store_id'        => $this->store_id,
            'feature_slug'    => $this->feature_slug,
            'suggestion_type' => $this->suggestion_type,
            'title'           => $this->title,
            'title_ar'        => $this->title_ar,
            'content_json'    => $this->content_json,
            'priority'        => $this->priority,
            'status'          => $this->status,
            'accepted_at'     => $this->accepted_at,
            'dismissed_at'    => $this->dismissed_at,
            'expires_at'      => $this->expires_at,
            'created_at'      => $this->created_at,
        ];
    }
}
