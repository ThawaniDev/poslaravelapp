<?php

namespace App\Domain\Support\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'support_ticket_id'=> $this->support_ticket_id,
            'sender_type'      => $this->sender_type,
            'sender_id'        => $this->sender_id,
            'message_text'     => $this->message_text,
            'attachments'      => $this->attachments,
            'is_internal_note' => (bool) $this->is_internal_note,
            'sent_at'          => $this->sent_at?->toIso8601String(),
        ];
    }
}
