<?php

namespace App\Domain\Support\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ticket_number'        => $this->ticket_number,
            'organization_id'      => $this->organization_id,
            'store_id'             => $this->store_id,
            'user_id'              => $this->user_id,
            'assigned_to'          => $this->assigned_to,
            'category'             => $this->category,
            'priority'             => $this->priority,
            'status'               => $this->status,
            'subject'              => $this->subject,
            'description'          => $this->description,
            'sla_badge'            => $this->sla_badge,
            'satisfaction_rating'  => $this->satisfaction_rating,
            'satisfaction_comment' => $this->satisfaction_comment,
            'sla_deadline_at'      => $this->sla_deadline_at?->toIso8601String(),
            'first_response_at'    => $this->first_response_at?->toIso8601String(),
            'resolved_at'          => $this->resolved_at?->toIso8601String(),
            'closed_at'            => $this->closed_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
            'messages_count'       => $this->whenLoaded('messages', fn () => $this->messages->count()),
            'messages'             => SupportTicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
