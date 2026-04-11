<?php

namespace App\Domain\CashierGamification\Models;

use App\Domain\CashierGamification\Enums\PerformancePeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierBadgeAward extends Model
{
    use HasUuids;

    protected $table = 'cashier_badge_awards';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'cashier_id',
        'badge_id',
        'snapshot_id',
        'earned_date',
        'period',
        'metric_value',
        'created_at',
    ];

    protected $casts = [
        'period' => PerformancePeriod::class,
        'earned_date' => 'date',
        'metric_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'cashier_id');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(CashierBadge::class, 'badge_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CashierPerformanceSnapshot::class, 'snapshot_id');
    }
}
