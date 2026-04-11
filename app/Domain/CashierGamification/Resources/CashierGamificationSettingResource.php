<?php

namespace App\Domain\CashierGamification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashierGamificationSettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'leaderboard_enabled' => (bool) $this->leaderboard_enabled,
            'badges_enabled' => (bool) $this->badges_enabled,
            'anomaly_detection_enabled' => (bool) $this->anomaly_detection_enabled,
            'shift_reports_enabled' => (bool) $this->shift_reports_enabled,
            'auto_generate_on_session_close' => (bool) $this->auto_generate_on_session_close,
            'anomaly_z_score_threshold' => (float) $this->anomaly_z_score_threshold,
            'risk_score_void_weight' => (float) $this->risk_score_void_weight,
            'risk_score_no_sale_weight' => (float) $this->risk_score_no_sale_weight,
            'risk_score_discount_weight' => (float) $this->risk_score_discount_weight,
            'risk_score_price_override_weight' => (float) $this->risk_score_price_override_weight,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
