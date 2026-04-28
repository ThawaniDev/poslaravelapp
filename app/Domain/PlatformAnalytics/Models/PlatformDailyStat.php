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
    ];

    protected $casts = [
        'total_gmv' => 'decimal:2',
        'total_mrr' => 'decimal:2',
        'date' => 'date',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }
}
