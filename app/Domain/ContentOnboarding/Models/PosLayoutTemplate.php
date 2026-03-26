<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosLayoutTemplate extends Model
{
    use HasUuids;

    protected $table = 'pos_layout_templates';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'business_type_id',
        'layout_key',
        'name',
        'name_ar',
        'description',
        'preview_image_url',
        'config',
        'is_default',
        'is_active',
        'sort_order',
        'canvas_columns',
        'canvas_rows',
        'canvas_gap_px',
        'canvas_padding_px',
        'breakpoints',
        'version',
        'is_locked',
        'clone_source_id',
        'published_at',
    ];

    protected $casts = [
        'config' => 'array',
        'breakpoints' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
    public function layoutPackageVisibility(): HasMany
    {
        return $this->hasMany(LayoutPackageVisibility::class);
    }
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserPreference::class, 'pos_layout_id');
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'layout_package_visibility',
            'pos_layout_template_id',
            'subscription_plan_id',
        );
    }

    public function widgetPlacements(): HasMany
    {
        return $this->hasMany(LayoutWidgetPlacement::class, 'pos_layout_template_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class, 'pos_layout_template_id');
    }

    public function marketplaceListing(): HasOne
    {
        return $this->hasOne(TemplateMarketplaceListing::class, 'pos_layout_template_id');
    }

    public function cloneSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'clone_source_id');
    }
}
