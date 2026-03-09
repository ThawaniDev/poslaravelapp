<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\BusinessType;
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

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
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
