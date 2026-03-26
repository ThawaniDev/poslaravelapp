<?php

namespace App\Domain\PlatformAnalytics\Models;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPlanStat extends Model
{
    use HasUuids;

    protected $table = 'platform_plan_stats';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'subscription_plan_id',
        'date',
        'active_count',
        'trial_count',
        'churned_count',
        'mrr',
    ];

    protected $casts = [
        'mrr' => 'decimal:2',
        'date' => 'date',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
