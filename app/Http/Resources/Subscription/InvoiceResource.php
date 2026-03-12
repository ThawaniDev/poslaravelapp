<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'amount' => (float) $this->amount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'status' => $this->status,
            'due_date' => $this->due_date?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'pdf_url' => $this->pdf_url,

            'line_items' => $this->whenLoaded('invoiceLineItems', fn () =>
                InvoiceLineItemResource::collection($this->invoiceLineItems)
            ),

            'subscription' => $this->whenLoaded('storeSubscription', fn () =>
                new StoreSubscriptionResource($this->storeSubscription)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
