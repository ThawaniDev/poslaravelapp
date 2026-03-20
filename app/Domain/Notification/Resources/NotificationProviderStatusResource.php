<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationProviderStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'provider'          => $this->provider?->value,
            'channel'           => $this->channel?->value,
            'is_enabled'        => $this->is_enabled,
            'priority'          => $this->priority,
            'is_healthy'        => $this->is_healthy,
            'last_success_at'   => $this->last_success_at?->toIso8601String(),
            'last_failure_at'   => $this->last_failure_at?->toIso8601String(),
            'failure_count_24h' => $this->failure_count_24h,
            'success_count_24h' => $this->success_count_24h,
            'avg_latency_ms'    => $this->avg_latency_ms,
            'disabled_reason'   => $this->disabled_reason,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
