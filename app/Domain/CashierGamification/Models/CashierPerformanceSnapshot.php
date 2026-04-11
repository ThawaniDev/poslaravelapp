<?php

namespace App\Domain\CashierGamification\Models;

use App\Domain\CashierGamification\Enums\PerformancePeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierPerformanceSnapshot extends Model
{
    use HasUuids;

    protected $table = 'cashier_performance_snapshots';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'cashier_id',
        'pos_session_id',
        'period_type',
        'date',
        'shift_start',
        'shift_end',
        'active_minutes',
        'total_transactions',
        'total_items_sold',
        'total_revenue',
        'total_discount_given',
        'avg_basket_size',
        'items_per_minute',
        'avg_transaction_time_seconds',
        'void_count',
        'void_amount',
        'void_rate',
        'return_count',
        'return_amount',
        'discount_count',
        'discount_rate',
        'price_override_count',
        'no_sale_count',
        'upsell_count',
        'upsell_rate',
        'cash_variance',
        'cash_variance_absolute',
        'risk_score',
        'anomaly_flags',
    ];

    protected $casts = [
        'period_type' => PerformancePeriod::class,
        'date' => 'date',
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'active_minutes' => 'integer',
        'total_transactions' => 'integer',
        'total_items_sold' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_discount_given' => 'decimal:2',
        'avg_basket_size' => 'decimal:2',
        'items_per_minute' => 'decimal:2',
        'avg_transaction_time_seconds' => 'integer',
        'void_count' => 'integer',
        'void_amount' => 'decimal:2',
        'void_rate' => 'decimal:4',
        'return_count' => 'integer',
        'return_amount' => 'decimal:2',
        'discount_count' => 'integer',
        'discount_rate' => 'decimal:4',
        'price_override_count' => 'integer',
        'no_sale_count' => 'integer',
        'upsell_count' => 'integer',
        'upsell_rate' => 'decimal:4',
        'cash_variance' => 'decimal:2',
        'cash_variance_absolute' => 'decimal:2',
        'risk_score' => 'decimal:2',
        'anomaly_flags' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'cashier_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\PosTerminal\Models\PosSession::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(CashierAnomaly::class, 'snapshot_id');
    }

    public function badgeAwards(): HasMany
    {
        return $this->hasMany(CashierBadgeAward::class, 'snapshot_id');
    }
}
