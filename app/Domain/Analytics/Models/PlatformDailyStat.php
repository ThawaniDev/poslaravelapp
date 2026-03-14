<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformDailyStat extends Model
{
    use HasUuids;

    protected $table = 'platform_daily_stats';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'date',
        'total_active_stores',
        'new_registrations',
        'total_orders',
        'total_gmv',
        'total_mrr',
        'churn_count',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_gmv' => 'decimal:2',
        'total_mrr' => 'decimal:2',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }
}
