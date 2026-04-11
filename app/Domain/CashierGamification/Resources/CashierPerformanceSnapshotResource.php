<?php

namespace App\Domain\CashierGamification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashierPerformanceSnapshotResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'cashier_id' => $this->cashier_id,
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
                'email' => $this->cashier->email,
            ]),
            'pos_session_id' => $this->pos_session_id,
            'date' => $this->date,
            'period_type' => $this->period_type?->value ?? $this->period_type,
            'shift_start' => $this->shift_start?->toISOString(),
            'shift_end' => $this->shift_end?->toISOString(),
            'active_minutes' => (int) $this->active_minutes,
            'total_transactions' => (int) $this->total_transactions,
            'total_items_sold' => (int) $this->total_items_sold,
            'total_revenue' => (float) $this->total_revenue,
            'total_discount_given' => (float) $this->total_discount_given,
            'avg_basket_size' => (float) $this->avg_basket_size,
            'items_per_minute' => (float) $this->items_per_minute,
            'avg_transaction_time_seconds' => (int) $this->avg_transaction_time_seconds,
            'void_count' => (int) $this->void_count,
            'void_amount' => (float) $this->void_amount,
            'void_rate' => (float) $this->void_rate,
            'return_count' => (int) $this->return_count,
            'return_amount' => (float) $this->return_amount,
            'discount_count' => (int) $this->discount_count,
            'discount_rate' => (float) $this->discount_rate,
            'price_override_count' => (int) $this->price_override_count,
            'no_sale_count' => (int) $this->no_sale_count,
            'upsell_count' => (int) $this->upsell_count,
            'upsell_rate' => (float) $this->upsell_rate,
            'cash_variance' => (float) $this->cash_variance,
            'cash_variance_absolute' => (float) $this->cash_variance_absolute,
            'risk_score' => (float) $this->risk_score,
            'anomaly_flags' => $this->anomaly_flags,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
