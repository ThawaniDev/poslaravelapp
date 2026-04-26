<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'return_id'        => $this->return_id,
            'payment_id'       => $this->payment_id,
            'method'           => $this->method?->value ?? $this->method,
            'amount'           => (float) $this->amount,
            'reference_number' => $this->reference_number,
            'status'           => $this->status?->value ?? $this->status,
            'processed_by'     => $this->processed_by,
            'created_at'       => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->toIso8601String() : null,
        ];
    }
}
