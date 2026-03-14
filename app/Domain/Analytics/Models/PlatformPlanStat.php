<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Subscription\Models\SubscriptionPlan;

class PlatformPlanStat extends Model
{
    use HasUuids;

    protected $table = 'platform_plan_stats';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'subscription_plan_id',
        'date',
        'active_stores',
        'trial_stores',
        'churned_stores',
        'revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'decimal:2',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
