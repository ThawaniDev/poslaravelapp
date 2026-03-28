<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPageContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Domain\ContentOnboarding\Models\PricingPageContent $this */
        return [
            'id' => $this->id,

            // ── Linked Plan ───────────────────────────────────────────────
            'plan' => $this->whenLoaded('subscriptionPlan', fn () => [
                'id'            => $this->subscriptionPlan->id,
                'name'          => $this->subscriptionPlan->name,
                'name_ar'       => $this->subscriptionPlan->name_ar,
                'slug'          => $this->subscriptionPlan->slug,
                'monthly_price' => (float) $this->subscriptionPlan->monthly_price,
                'annual_price'  => $this->subscriptionPlan->annual_price
                    ? (float) $this->subscriptionPlan->annual_price
                    : null,
                'trial_days'    => $this->subscriptionPlan->trial_days,
                'is_highlighted' => (bool) $this->subscriptionPlan->is_highlighted,
            ]),

            // ── Hero / Display ────────────────────────────────────────────
            'hero_title'     => $this->hero_title,
            'hero_title_ar'  => $this->hero_title_ar,
            'hero_subtitle'  => $this->hero_subtitle,
            'hero_subtitle_ar' => $this->hero_subtitle_ar,

            // ── Badge ─────────────────────────────────────────────────────
            'highlight_badge'    => $this->highlight_badge,
            'highlight_badge_ar' => $this->highlight_badge_ar,
            'highlight_color'    => $this->highlight_color ?? 'primary',
            'is_highlighted'     => (bool) $this->is_highlighted,

            // ── CTA ───────────────────────────────────────────────────────
            'cta_label'               => $this->cta_label,
            'cta_label_ar'            => $this->cta_label_ar,
            'cta_secondary_label'     => $this->cta_secondary_label,
            'cta_secondary_label_ar'  => $this->cta_secondary_label_ar,
            'cta_url'                 => $this->cta_url,

            // ── Pricing Display ───────────────────────────────────────────
            'price_prefix'              => $this->price_prefix,
            'price_prefix_ar'           => $this->price_prefix_ar,
            'price_suffix'              => $this->price_suffix,
            'price_suffix_ar'           => $this->price_suffix_ar,
            'annual_discount_label'     => $this->annual_discount_label,
            'annual_discount_label_ar'  => $this->annual_discount_label_ar,
            'trial_label'               => $this->trial_label,
            'trial_label_ar'            => $this->trial_label_ar,
            'money_back_days'           => $this->money_back_days,

            // ── Content ───────────────────────────────────────────────────
            'feature_bullet_list'   => $this->feature_bullet_list   ?? [],
            'feature_categories'    => $this->feature_categories    ?? [],
            'faq'                   => $this->faq                   ?? [],
            'testimonials'          => $this->testimonials          ?? [],
            'comparison_highlights' => $this->comparison_highlights ?? [],

            // ── SEO ───────────────────────────────────────────────────────
            'meta_title'           => $this->meta_title,
            'meta_title_ar'        => $this->meta_title_ar,
            'meta_description'     => $this->meta_description,
            'meta_description_ar'  => $this->meta_description_ar,

            // ── Visuals ───────────────────────────────────────────────────
            'color_theme'    => $this->color_theme    ?? 'primary',
            'card_icon'      => $this->card_icon,
            'card_image_url' => $this->card_image_url,

            // ── Publishing ────────────────────────────────────────────────
            'is_published' => (bool) $this->is_published,
            'sort_order'   => (int) $this->sort_order,

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
