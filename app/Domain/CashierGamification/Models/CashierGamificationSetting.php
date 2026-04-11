<?php

namespace App\Domain\CashierGamification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierGamificationSetting extends Model
{
    use HasUuids;

    protected $table = 'cashier_gamification_settings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'leaderboard_enabled',
        'badges_enabled',
        'anomaly_detection_enabled',
        'shift_reports_enabled',
        'auto_generate_on_session_close',
        'risk_score_void_weight',
        'risk_score_no_sale_weight',
        'risk_score_discount_weight',
        'risk_score_price_override_weight',
        'anomaly_z_score_threshold',
    ];

    protected $casts = [
        'leaderboard_enabled' => 'boolean',
        'badges_enabled' => 'boolean',
        'anomaly_detection_enabled' => 'boolean',
        'shift_reports_enabled' => 'boolean',
        'auto_generate_on_session_close' => 'boolean',
        'risk_score_void_weight' => 'decimal:2',
        'risk_score_no_sale_weight' => 'decimal:2',
        'risk_score_discount_weight' => 'decimal:2',
        'risk_score_price_override_weight' => 'decimal:2',
        'anomaly_z_score_threshold' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }
}
