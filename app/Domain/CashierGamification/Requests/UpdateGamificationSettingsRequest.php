<?php

namespace App\Domain\CashierGamification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGamificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leaderboard_enabled' => ['sometimes', 'boolean'],
            'badges_enabled' => ['sometimes', 'boolean'],
            'anomaly_detection_enabled' => ['sometimes', 'boolean'],
            'shift_reports_enabled' => ['sometimes', 'boolean'],
            'auto_generate_on_session_close' => ['sometimes', 'boolean'],
            'anomaly_z_score_threshold' => ['sometimes', 'numeric', 'min:0.5', 'max:5'],
            'risk_score_void_weight' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'risk_score_no_sale_weight' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'risk_score_discount_weight' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'risk_score_price_override_weight' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
