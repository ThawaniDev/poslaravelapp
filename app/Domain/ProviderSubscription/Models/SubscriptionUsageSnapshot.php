<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Subscription\Enums\SubscriptionResourceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsageSnapshot extends Model
{
    use HasUuids;

    protected $table = 'subscription_usage_snapshots';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'resource_type',
        'current_count',
        'plan_limit',
        'snapshot_date',
    ];

    protected $casts = [
        'resource_type' => SubscriptionResourceType::class,
        'snapshot_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
