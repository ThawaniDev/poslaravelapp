<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'monthly_price' => (float) $this->monthly_price,
            'annual_price' => $this->annual_price ? (float) $this->annual_price : null,
            'trial_days' => $this->trial_days,
            'grace_period_days' => $this->grace_period_days,
            'is_active' => (bool) $this->is_active,
            'is_highlighted' => (bool) $this->is_highlighted,
            'sort_order' => $this->sort_order,
            'business_type' => $this->business_type,
            'softpos_free_eligible' => (bool) $this->softpos_free_eligible,
            'softpos_free_threshold' => $this->softpos_free_threshold ? (int) $this->softpos_free_threshold : null,
            'softpos_free_threshold_amount' => $this->softpos_free_threshold_amount ? (float) $this->softpos_free_threshold_amount : null,
            'softpos_free_threshold_period' => $this->softpos_free_threshold_period,

            'features' => $this->whenLoaded('planFeatureToggles', fn () =>
                $this->planFeatureToggles->map(fn ($t) => [
                    'feature_key' => $t->feature_key,
                    'name'        => $t->name,
                    'name_ar'     => $t->name_ar,
                    'is_enabled'  => (bool) $t->is_enabled,
                ])
            ),

            'limits' => $this->whenLoaded('planLimits', fn () =>
                $this->planLimits->map(fn ($l) => [
                    'limit_key' => $l->limit_key,
                    'limit_value' => (int) $l->limit_value,
                    'price_per_extra_unit' => $l->price_per_extra_unit ? (float) $l->price_per_extra_unit : null,
                ])
            ),

            'pricing_content' => $this->whenLoaded('pricingPageContent', fn () =>
                $this->pricingPageContent
                    ? new \App\Http\Resources\Content\PricingPageContentResource($this->pricingPageContent)
                    : null
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
