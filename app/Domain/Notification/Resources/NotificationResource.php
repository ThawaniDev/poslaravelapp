<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->action_url,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'priority' => $this->priority ?? 'normal',
            'channel' => $this->channel ?? 'in_app',
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at ? Carbon::parse($this->read_at)->toIso8601String() : null,
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at)->toIso8601String() : null,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
