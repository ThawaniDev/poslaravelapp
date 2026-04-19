<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Announcement\Models\PaymentReminder;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Payment\Enums\SubscriptionPaymentMethod;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Core\Models\Organization;
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
        'organization_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'payment_method',
        'cancelled_at',
        'is_softpos_free',
        'softpos_transaction_count',
        'softpos_count_reset_at',
        'original_amount',
        'discount_reason',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'billing_cycle' => BillingCycle::class,
        'payment_method' => SubscriptionPaymentMethod::class,
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_softpos_free' => 'boolean',
        'softpos_transaction_count' => 'integer',
        'softpos_count_reset_at' => 'datetime',
        'original_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
