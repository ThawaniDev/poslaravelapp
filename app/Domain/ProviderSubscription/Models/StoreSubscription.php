<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Payment\Enums\SubscriptionPaymentMethod;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreSubscription extends Model
{
    use HasUuids;

    protected $table = 'store_subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'payment_method',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'billing_cycle' => BillingCycle::class,
        'payment_method' => SubscriptionPaymentMethod::class,
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
    public function subscriptionCredits(): HasMany
    {
        return $this->hasMany(SubscriptionCredit::class);
    }
    public function cancellationReasons(): HasMany
    {
        return $this->hasMany(CancellationReason::class);
    }
    public function paymentReminders(): HasMany
    {
        return $this->hasMany(PaymentReminder::class);
    }
}
