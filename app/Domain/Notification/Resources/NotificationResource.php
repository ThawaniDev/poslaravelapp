<?php

namespace App\Domain\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Serve title/message in the requesting user's preferred locale.
        // Arabic content is stored in metadata.title_ar / metadata.body_ar.
        // Old notifications without these keys fall back to the stored title/message (English).
        $userLocale = $request->user()?->locale ?? 'ar';
        $isArabic = str_starts_with(strtolower($userLocale), 'ar');

        $meta = is_array($this->metadata) ? $this->metadata : [];

        $title   = ($isArabic && !empty($meta['title_ar'])) ? $meta['title_ar'] : $this->title;
        $message = ($isArabic && !empty($meta['body_ar']))  ? $meta['body_ar']  : $this->message;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'category' => $this->category,
            'title' => $title,
            'message' => $message,
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
