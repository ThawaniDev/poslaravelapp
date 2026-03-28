<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPageContent extends Model
{
    use HasUuids;

    protected $table = 'pricing_page_content';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'subscription_plan_id',
        'hero_title', 'hero_title_ar',
        'hero_subtitle', 'hero_subtitle_ar',
        'highlight_badge', 'highlight_badge_ar',
        'highlight_color', 'is_highlighted',
        'cta_label', 'cta_label_ar',
        'cta_secondary_label', 'cta_secondary_label_ar',
        'cta_url',
        'price_prefix', 'price_prefix_ar',
        'price_suffix', 'price_suffix_ar',
        'annual_discount_label', 'annual_discount_label_ar',
        'trial_label', 'trial_label_ar',
        'money_back_days',
        'feature_bullet_list',
        'feature_categories',
        'faq',
        'testimonials',
        'comparison_highlights',
        'meta_title', 'meta_title_ar',
        'meta_description', 'meta_description_ar',
        'color_theme', 'card_icon', 'card_image_url',
        'is_published', 'sort_order',
    ];

    protected $casts = [
        'feature_bullet_list'   => 'array',
        'feature_categories'    => 'array',
        'faq'                   => 'array',
        'testimonials'          => 'array',
        'comparison_highlights' => 'array',
        'is_highlighted'        => 'boolean',
        'is_published'          => 'boolean',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
