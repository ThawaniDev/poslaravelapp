<?php

namespace App\Domain\Debit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebitAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'debit_id' => $this->debit_id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
            'allocated_by' => $this->allocated_by,
            'allocated_by_name' => $this->whenLoaded('allocatedBy', fn () => $this->allocatedBy?->name),
            'allocated_at' => $this->allocated_at?->toISOString(),
        ];
    }
}
