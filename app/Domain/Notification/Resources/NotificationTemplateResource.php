<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'event_key'           => $this->event_key,
            'channel'             => $this->channel?->value,
            'title'               => $this->title,
            'title_ar'            => $this->title_ar,
            'body'                => $this->body,
            'body_ar'             => $this->body_ar,
            'available_variables' => $this->available_variables,
            'is_active'           => $this->is_active,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
