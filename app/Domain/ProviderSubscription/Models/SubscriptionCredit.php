<?php

namespace App\Domain\ProviderSubscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCredit extends Model
{
    use HasUuids;

    protected $table = 'subscription_credits';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_subscription_id',
        'applied_by',
        'amount',
        'reason',
        'applied_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function storeSubscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class);
    }
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'applied_by');
    }
}
