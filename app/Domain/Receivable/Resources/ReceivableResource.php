<?php

namespace App\Domain\Receivable\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'store_id' => $this->store_id,
            'customer_id' => $this->customer_id,
            'reference_number' => $this->reference_number,
            'receivable_type' => $this->receivable_type?->value ?? $this->receivable_type,
            'source' => $this->source?->value ?? $this->source,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'amount' => (float) $this->amount,
            'status' => $this->status?->value ?? $this->status,
            'remaining_balance' => (float) $this->remaining_balance,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'sync_version' => $this->sync_version,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ]),
            'payments' => ReceivablePaymentResource::collection($this->whenLoaded('payments')),
            'logs' => ReceivableLogResource::collection($this->whenLoaded('logs')),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'settled_by' => $this->settled_by,
            'settled_by_name' => $this->whenLoaded('settledBy', fn () => $this->settledBy?->name),
            'settled_at' => $this->settled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
