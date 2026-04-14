<?php

namespace App\Domain\WameedAI\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIUsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'store_name'        => $this->whenLoaded('store', fn () => $this->store?->name),
            'user_id'           => $this->user_id,
            'user_name'         => $this->whenLoaded('user', fn () => $this->user?->name),
            'feature_slug'      => $this->feature_slug,
            'model_used'        => $this->model_used,
            'input_tokens'      => (int) $this->input_tokens,
            'output_tokens'     => (int) $this->output_tokens,
            'total_tokens'      => (int) $this->total_tokens,
            'estimated_cost_usd' => (float) ($this->billed_cost_usd > 0 ? $this->billed_cost_usd : $this->estimated_cost_usd),
            'status'            => $this->status,
            'latency_ms'        => (int) $this->latency_ms,
            'response_cached'   => (bool) $this->response_cached,
            'error_message'     => $this->error_message,
            'request_messages'  => $this->request_messages ? json_decode($this->request_messages, true) : null,
            'created_at'        => $this->created_at,
        ];
    }
}
