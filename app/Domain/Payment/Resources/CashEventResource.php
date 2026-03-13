<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'cash_session_id' => $this->cash_session_id,
            'type'            => $this->type?->value ?? $this->type,
            'amount'          => (float) $this->amount,
            'reason'          => $this->reason,
            'notes'           => $this->notes,
            'performed_by'    => $this->performed_by,
        ];
    }
}
