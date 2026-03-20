<?php

namespace App\Domain\Report\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailySalesSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'store_id'           => $this->store_id,
            'date'               => $this->date?->toDateString(),
            'total_transactions' => $this->total_transactions,
            'total_revenue'      => $this->total_revenue,
            'total_cost'         => $this->total_cost,
            'total_discount'     => $this->total_discount,
            'total_tax'          => $this->total_tax,
            'total_refunds'      => $this->total_refunds,
            'net_revenue'        => $this->net_revenue,
            'cash_revenue'       => $this->cash_revenue,
            'card_revenue'       => $this->card_revenue,
            'other_revenue'      => $this->other_revenue,
            'avg_basket_size'    => $this->avg_basket_size,
            'unique_customers'   => $this->unique_customers,
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
