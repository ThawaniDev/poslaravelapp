<?php

namespace App\Domain\CashierGamification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashierAnomalyResource extends JsonResource
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
            'snapshot_id' => $this->snapshot_id,
            'anomaly_type' => $this->anomaly_type?->value ?? $this->anomaly_type,
            'severity' => $this->severity?->value ?? $this->severity,
            'risk_score' => (float) $this->risk_score,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'metric_name' => $this->metric_name,
            'metric_value' => (float) $this->metric_value,
            'store_average' => (float) $this->store_average,
            'store_stddev' => (float) $this->store_stddev,
            'z_score' => (float) $this->z_score,
            'reference_ids' => $this->reference_ids,
            'detected_date' => $this->detected_date,
            'is_reviewed' => (bool) $this->is_reviewed,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
