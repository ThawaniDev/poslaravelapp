<?php

namespace App\Domain\Debit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'store_id' => $this->store_id,
            'customer_id' => $this->customer_id,
            'reference_number' => $this->reference_number,
            'debit_type' => $this->debit_type?->value ?? $this->debit_type,
            'source' => $this->source?->value ?? $this->source,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'amount' => (float) $this->amount,
            'status' => $this->status?->value ?? $this->status,
            'remaining_balance' => (float) $this->remaining_balance,
            'notes' => $this->notes,
            'sync_version' => $this->sync_version,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ]),
            'allocations' => DebitAllocationResource::collection($this->whenLoaded('allocations')),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'allocated_by' => $this->allocated_by,
            'allocated_by_name' => $this->whenLoaded('allocatedBy', fn () => $this->allocatedBy?->name),
            'allocated_at' => $this->allocated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
