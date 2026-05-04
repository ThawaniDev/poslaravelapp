<?php

namespace App\Domain\PosTerminal\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PosSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'register_id' => $this->register_id,
            'register_name' => $this->whenLoaded('register', fn () => $this->register?->name),
            'cashier_id' => $this->cashier_id,
            'cashier_name' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'status' => $this->status?->value ?? $this->status,
            'opening_cash' => (float) $this->opening_cash,
            'closing_cash' => $this->closing_cash !== null ? (float) $this->closing_cash : null,
            'expected_cash' => $this->expected_cash !== null ? (float) $this->expected_cash : null,
            'cash_difference' => $this->cash_difference !== null ? (float) $this->cash_difference : null,
            'total_cash_sales' => (float) $this->total_cash_sales,
            'total_card_sales' => (float) $this->total_card_sales,
            'total_softpos_sales' => (float) ($this->total_softpos_sales ?? 0),
            'total_other_sales' => (float) $this->total_other_sales,
            'total_refunds' => (float) $this->total_refunds,
            'total_voids' => (float) $this->total_voids,
            'transaction_count' => (int) $this->transaction_count,
            'opened_at' => $this->opened_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'z_report_printed' => (bool) $this->z_report_printed,
        ];
    }
}
