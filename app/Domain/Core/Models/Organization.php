<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\BusinessType;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasUuids;

    protected $table = 'organizations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'cr_number',
        'vat_number',
        'business_type',
        'logo_url',
        'country',
        'city',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'business_type' => BusinessType::class,
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $org): void {
            if (empty($org->slug) && ! empty($org->name)) {
                $base = \Illuminate\Support\Str::slug($org->name) ?: \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $org->slug = $slug;
            }
        });
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
    public function subscription(): HasOne
    {
        return $this->hasOne(StoreSubscription::class);
    }
    public function subscriptionUsageSnapshots(): HasMany
    {
        return $this->hasMany(SubscriptionUsageSnapshot::class);
    }
    public function providerLimitOverrides(): HasMany
    {
        return $this->hasMany(ProviderLimitOverride::class);
    }
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    public function providerNotes(): HasMany
    {
        return $this->hasMany(ProviderNote::class);
    }
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    public function productVariantGroups(): HasMany
    {
        return $this->hasMany(ProductVariantGroup::class);
    }
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }
    public function loyaltyConfig(): HasOne
    {
        return $this->hasOne(LoyaltyConfig::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class);
    }
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
    public function labelTemplates(): HasMany
    {
        return $this->hasMany(LabelTemplate::class);
    }
}
