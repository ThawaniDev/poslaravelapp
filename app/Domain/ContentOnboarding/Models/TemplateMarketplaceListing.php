<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Enums\MarketplacePricingType;
use App\Domain\ContentOnboarding\Enums\SubscriptionInterval;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateMarketplaceListing extends Model
{
    use HasUuids;

    protected $table = 'template_marketplace_listings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'pos_layout_template_id', 'theme_id', 'publisher_name',
        'publisher_avatar_url', 'title', 'title_ar', 'description',
        'description_ar', 'short_description', 'short_description_ar',
        'preview_images', 'demo_video_url', 'pricing_type', 'price_amount',
        'price_currency', 'subscription_interval', 'category_id', 'tags',
        'version', 'changelog', 'download_count', 'average_rating',
        'review_count', 'is_featured', 'is_verified', 'status',
        'rejection_reason', 'approved_by', 'approved_at', 'published_at',
    ];

    protected $casts = [
        'pricing_type' => MarketplacePricingType::class,
        'subscription_interval' => SubscriptionInterval::class,
        'status' => MarketplaceListingStatus::class,
        'preview_images' => 'array',
        'tags' => 'array',
        'price_amount' => 'decimal:2',
        'average_rating' => 'decimal:1',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function layoutTemplate(): BelongsTo
    {
        return $this->belongsTo(PosLayoutTemplate::class, 'pos_layout_template_id');
    }

    public function bundledTheme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TemplatePurchase::class, 'marketplace_listing_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TemplateReview::class, 'marketplace_listing_id');
    }
}
