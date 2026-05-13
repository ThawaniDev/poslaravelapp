<?php

namespace App\Domain\Subscription\Models;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $table = 'subscription_plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'monthly_price',
        'annual_price',
        'trial_days',
        'grace_period_days',
        'is_active',
        'is_highlighted',
        'softpos_free_eligible',
        'softpos_free_threshold',
        'softpos_free_threshold_amount',
        'softpos_free_threshold_period',
        'sort_order',
        'business_type',
        'tier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_highlighted' => 'boolean',
        'softpos_free_eligible' => 'boolean',
        'softpos_free_threshold' => 'integer',
        'softpos_free_threshold_amount' => 'decimal:3',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->name_ar) && ! empty($plan->name)) {
                $plan->name_ar = $plan->name;
            }
            if (empty($plan->slug) && ! empty($plan->name)) {
                $base = \Illuminate\Support\Str::slug($plan->name) ?: \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $plan->slug = $slug;
            }
        });
    }

    public function planFeatureToggles(): HasMany
    {
        return $this->hasMany(PlanFeatureToggle::class);
    }
    public function planLimits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }
    public function themePackageVisibility(): HasMany
    {
        return $this->hasMany(ThemePackageVisibility::class);
    }
    public function layoutPackageVisibility(): HasMany
    {
        return $this->hasMany(LayoutPackageVisibility::class);
    }
    public function receiptTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(ReceiptTemplatePackageVisibility::class);
    }
    public function cfdThemePackageVisibility(): HasMany
    {
        return $this->hasMany(CfdThemePackageVisibility::class);
    }
    public function signageTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(SignageTemplatePackageVisibility::class);
    }
    public function labelTemplatePackageVisibility(): HasMany
    {
        return $this->hasMany(LabelTemplatePackageVisibility::class);
    }
    public function pricingPageContent(): HasOne
    {
        return $this->hasOne(PricingPageContent::class);
    }
    public function storeSubscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }
    public function customRolePackageConfig(): HasOne
    {
        return $this->hasOne(CustomRolePackageConfig::class);
    }
    public function platformPlanStats(): HasMany
    {
        return $this->hasMany(PlatformPlanStat::class);
    }
}
