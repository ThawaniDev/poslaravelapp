<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationEventLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'notification_id' => $this->notification_id,
            'channel'         => $this->channel?->value,
            'status'          => $this->status?->value,
            'error_message'   => $this->error_message,
            'sent_at'         => $this->sent_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
