<?php

namespace App\Domain\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'terminal_id'   => $this->terminal_id,
            'opened_by'     => $this->opened_by,
            'closed_by'     => $this->closed_by,
            'opening_float' => (float) $this->opening_float,
            'expected_cash' => (float) $this->expected_cash,
            'actual_cash'   => $this->actual_cash !== null ? (float) $this->actual_cash : null,
            'variance'      => $this->variance !== null ? (float) $this->variance : null,
            'status'        => $this->status?->value ?? $this->status,
            'opened_at'     => $this->opened_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'close_notes'   => $this->close_notes,
            'cash_events'   => CashEventResource::collection($this->whenLoaded('cashEvents')),
            'expenses'      => ExpenseResource::collection($this->whenLoaded('expenses')),
        ];
    }
}
