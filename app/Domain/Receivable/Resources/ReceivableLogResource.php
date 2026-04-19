<?php

namespace App\Domain\Receivable\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receivable_id' => $this->receivable_id,
            'event' => $this->event,
            'from_value' => $this->from_value,
            'to_value' => $this->to_value,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'note' => $this->note,
            'meta' => $this->meta,
            'actor_id' => $this->actor_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
