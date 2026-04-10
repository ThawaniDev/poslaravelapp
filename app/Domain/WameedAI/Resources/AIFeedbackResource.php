<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ai_usage_log_id' => $this->ai_usage_log_id,
            'store_id'        => $this->store_id,
            'user_id'         => $this->user_id,
            'rating'          => (int) $this->rating,
            'feedback_text'   => $this->feedback_text,
            'is_helpful'      => $this->is_helpful,
            'created_at'      => $this->created_at,
        ];
    }
}
