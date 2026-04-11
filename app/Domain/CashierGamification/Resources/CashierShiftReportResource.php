<?php

namespace App\Domain\CashierGamification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashierShiftReportResource extends JsonResource
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
            'report_date' => $this->report_date,
            'shift_start' => $this->shift_start?->toISOString(),
            'shift_end' => $this->shift_end?->toISOString(),
            'total_transactions' => (int) $this->total_transactions,
            'total_revenue' => (float) $this->total_revenue,
            'total_items' => (int) $this->total_items,
            'items_per_minute' => (float) $this->items_per_minute,
            'avg_basket_size' => (float) $this->avg_basket_size,
            'void_count' => (int) $this->void_count,
            'void_amount' => (float) $this->void_amount,
            'return_count' => (int) $this->return_count,
            'return_amount' => (float) $this->return_amount,
            'discount_count' => (int) $this->discount_count,
            'discount_amount' => (float) $this->discount_amount,
            'no_sale_count' => (int) $this->no_sale_count,
            'price_override_count' => (int) $this->price_override_count,
            'cash_variance' => (float) $this->cash_variance,
            'upsell_count' => (int) $this->upsell_count,
            'upsell_rate' => (float) $this->upsell_rate,
            'risk_score' => (float) $this->risk_score,
            'risk_level' => $this->risk_level?->value ?? $this->risk_level,
            'anomaly_count' => (int) $this->anomaly_count,
            'badges_earned' => $this->badges_earned,
            'summary_en' => $this->summary_en,
            'summary_ar' => $this->summary_ar,
            'sent_to_owner' => (bool) $this->sent_to_owner,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
