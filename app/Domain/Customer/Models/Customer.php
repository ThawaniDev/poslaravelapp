<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'customers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'phone',
        'email',
        'address',
        'date_of_birth',
        'loyalty_code',
        'loyalty_points',
        'store_credit_balance',
        'group_id',
        'tax_registration_number',
        'notes',
        'total_spend',
        'visit_count',
        'last_visit_at',
        'sync_version',
    ];

    protected $casts = [
        'store_credit_balance' => 'decimal:2',
        'total_spend' => 'decimal:2',
        'date_of_birth' => 'date',
        'last_visit_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
    public function storeCreditTransactions(): HasMany
    {
        return $this->hasMany(StoreCreditTransaction::class);
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
    public function giftRegistries(): HasMany
    {
        return $this->hasMany(GiftRegistry::class);
    }
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
    public function customerChallengeProgress(): HasMany
    {
        return $this->hasMany(CustomerChallengeProgress::class);
    }
    public function customerBadges(): HasMany
    {
        return $this->hasMany(CustomerBadge::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function heldCarts(): HasMany
    {
        return $this->hasMany(HeldCart::class);
    }
    public function taxExemptions(): HasMany
    {
        return $this->hasMany(TaxExemption::class);
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    public function pendingOrders(): HasMany
    {
        return $this->hasMany(PendingOrder::class);
    }
    public function buybackTransactions(): HasMany
    {
        return $this->hasMany(BuybackTransaction::class);
    }
    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }
    public function tradeInRecords(): HasMany
    {
        return $this->hasMany(TradeInRecord::class);
    }
    public function flowerSubscriptions(): HasMany
    {
        return $this->hasMany(FlowerSubscription::class);
    }
    public function customCakeOrders(): HasMany
    {
        return $this->hasMany(CustomCakeOrder::class);
    }
}
