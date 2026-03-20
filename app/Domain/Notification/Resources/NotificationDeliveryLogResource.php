<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'notification_id'     => $this->notification_id,
            'channel'             => $this->channel?->value,
            'provider'            => $this->provider,
            'recipient'           => $this->recipient,
            'status'              => $this->status?->value,
            'provider_message_id' => $this->provider_message_id,
            'error_message'       => $this->error_message,
            'latency_ms'          => $this->latency_ms,
            'is_fallback'         => $this->is_fallback,
            'attempted_providers' => $this->attempted_providers,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
