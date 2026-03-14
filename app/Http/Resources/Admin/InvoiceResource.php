<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'store_subscription_id' => $this->store_subscription_id,
            'invoice_number' => $this->invoice_number,
            'amount' => (float) $this->amount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'status' => $status instanceof \BackedEnum ? $status->value : $status,
            'due_date' => $this->due_date instanceof \DateTimeInterface
                ? $this->due_date->toIso8601String()
                : $this->due_date,
            'paid_at' => $this->paid_at instanceof \DateTimeInterface
                ? $this->paid_at->toIso8601String()
                : $this->paid_at,
            'pdf_url' => $this->pdf_url,
            'line_items' => $this->when(
                $this->relationLoaded('invoiceLineItems'),
                fn () => $this->invoiceLineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ]),
            ),
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
