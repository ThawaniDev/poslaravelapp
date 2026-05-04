<?php

namespace App\Domain\PlatformAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformDailyStat extends Model
{
    use HasUuids;

    protected $table = 'platform_daily_stats';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'date',
        'total_active_stores',
        'new_registrations',
        'total_orders',
        'total_gmv',
        'total_mrr',
        'churn_count',
        'arr',
        'avg_revenue_per_store',
        'refund_count',
        // SoftPOS revenue columns
        'softpos_transaction_count',
        'softpos_volume',
        'softpos_platform_fees',
        'softpos_gateway_fees',
        'softpos_margin',
    ];

    protected $casts = [
        'total_gmv'                 => 'decimal:2',
        'total_mrr'                 => 'decimal:2',
        'arr'                       => 'decimal:2',
        'avg_revenue_per_store'     => 'decimal:2',
        'date'                      => 'date',
        // SoftPOS
        'softpos_transaction_count' => 'integer',
        'softpos_volume'            => 'decimal:3',
        'softpos_platform_fees'     => 'decimal:3',
        'softpos_gateway_fees'      => 'decimal:3',
        'softpos_margin'            => 'decimal:3',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }
}
