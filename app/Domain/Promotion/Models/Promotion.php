<?php

namespace App\Domain\Promotion\Models;

use App\Domain\Promotion\Enums\PromotionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasUuids;

    protected $table = 'promotions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'get_discount_percent',
        'bundle_price',
        'min_order_total',
        'min_item_quantity',
        'valid_from',
        'valid_to',
        'active_days',
        'active_time_from',
        'active_time_to',
        'max_uses',
        'max_uses_per_customer',
        'is_stackable',
        'is_active',
        'is_coupon',
        'usage_count',
        'sync_version',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'active_days' => 'array',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
        'is_coupon' => 'boolean',
        'discount_value' => 'decimal:2',
        'get_discount_percent' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'min_order_total' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function promotionProducts(): HasMany
    {
        return $this->hasMany(PromotionProduct::class);
    }
    public function promotionCategories(): HasMany
    {
        return $this->hasMany(PromotionCategory::class);
    }
    public function promotionCustomerGroups(): HasMany
    {
        return $this->hasMany(PromotionCustomerGroup::class);
    }
    public function couponCodes(): HasMany
    {
        return $this->hasMany(CouponCode::class);
    }
    public function promotionUsageLog(): HasMany
    {
        return $this->hasMany(PromotionUsageLog::class);
    }
    public function bundleProducts(): HasMany
    {
        return $this->hasMany(BundleProduct::class);
    }
}
