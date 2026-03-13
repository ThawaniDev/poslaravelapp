<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'code'            => $this->code,
            'barcode'         => $this->barcode,
            'initial_amount'  => (float) $this->initial_amount,
            'balance'         => (float) $this->balance,
            'recipient_name'  => $this->recipient_name,
            'status'          => $this->status?->value ?? $this->status,
            'issued_by'       => $this->issued_by,
            'issued_at_store' => $this->issued_at_store,
            'expires_at'      => $this->expires_at?->toDateString(),
        ];
    }
}
