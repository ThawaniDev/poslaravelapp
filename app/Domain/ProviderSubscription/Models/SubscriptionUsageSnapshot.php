<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Core\Models\Organization;
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
        'organization_id',
        'resource_type',
        'current_count',
        'plan_limit',
        'snapshot_date',
    ];

    protected $casts = [
        'resource_type' => SubscriptionResourceType::class,
        'snapshot_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
