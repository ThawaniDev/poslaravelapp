<?php

namespace App\Domain\CashierGamification\Models;

use App\Domain\CashierGamification\Enums\AnomalySeverity;
use App\Domain\CashierGamification\Enums\AnomalyType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierAnomaly extends Model
{
    use HasUuids;

    protected $table = 'cashier_anomalies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'cashier_id',
        'snapshot_id',
        'anomaly_type',
        'severity',
        'risk_score',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'metric_name',
        'metric_value',
        'store_average',
        'store_stddev',
        'z_score',
        'reference_ids',
        'is_reviewed',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'detected_date',
    ];

    protected $casts = [
        'anomaly_type' => AnomalyType::class,
        'severity' => AnomalySeverity::class,
        'risk_score' => 'decimal:2',
        'metric_value' => 'decimal:2',
        'store_average' => 'decimal:2',
        'store_stddev' => 'decimal:2',
        'z_score' => 'decimal:2',
        'reference_ids' => 'array',
        'is_reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'detected_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'cashier_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'reviewed_by');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CashierPerformanceSnapshot::class, 'snapshot_id');
    }
}
