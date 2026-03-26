<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    use HasUuids;

    protected $table = 'themes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'primary_color',
        'secondary_color',
        'background_color',
        'text_color',
        'is_active',
        'is_system',
        'typography_config',
        'spacing_config',
        'border_config',
        'shadow_config',
        'animation_config',
        'css_variables',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'typography_config' => 'array',
        'spacing_config' => 'array',
        'border_config' => 'array',
        'shadow_config' => 'array',
        'animation_config' => 'array',
        'css_variables' => 'array',
    ];

    public function themePackageVisibility(): HasMany
    {
        return $this->hasMany(ThemePackageVisibility::class);
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'theme_package_visibility',
            'theme_id',
            'subscription_plan_id',
        );
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ThemeVariable::class, 'theme_id');
    }

    public function marketplaceListings(): HasMany
    {
        return $this->hasMany(TemplateMarketplaceListing::class, 'theme_id');
    }
}
