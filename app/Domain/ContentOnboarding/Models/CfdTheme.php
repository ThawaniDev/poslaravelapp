<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\AnimationStyle;
use App\Domain\ContentOnboarding\Enums\CfdCartLayout;
use App\Domain\ContentOnboarding\Enums\CfdIdleLayout;
use App\Domain\ContentOnboarding\Enums\ThankYouAnimation;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfdTheme extends Model
{
    use HasUuids;

    protected $table = 'cfd_themes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'background_color',
        'text_color',
        'accent_color',
        'font_family',
        'cart_layout',
        'idle_layout',
        'animation_style',
        'transition_seconds',
        'show_store_logo',
        'show_running_total',
        'thank_you_animation',
        'is_active',
    ];

    protected $casts = [
        'cart_layout' => CfdCartLayout::class,
        'idle_layout' => CfdIdleLayout::class,
        'animation_style' => AnimationStyle::class,
        'thank_you_animation' => ThankYouAnimation::class,
        'show_store_logo' => 'boolean',
        'show_running_total' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cfdThemePackageVisibility(): HasMany
    {
        return $this->hasMany(CfdThemePackageVisibility::class);
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'cfd_theme_package_visibility',
            'cfd_theme_id',
            'subscription_plan_id',
        );
    }
}
