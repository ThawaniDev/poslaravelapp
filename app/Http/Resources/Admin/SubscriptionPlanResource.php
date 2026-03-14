<?php

namespace App\Http\Resources\Admin;

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
            'features' => $this->when(
                $this->relationLoaded('planFeatureToggles'),
                fn () => $this->planFeatureToggles->map(fn ($f) => [
                    'id' => $f->id,
                    'feature_key' => $f->feature_key,
                    'is_enabled' => (bool) $f->is_enabled,
                ]),
            ),
            'limits' => $this->when(
                $this->relationLoaded('planLimits'),
                fn () => $this->planLimits->map(fn ($l) => [
                    'id' => $l->id,
                    'limit_key' => $l->limit_key,
                    'limit_value' => $l->limit_value,
                    'price_per_extra_unit' => $l->price_per_extra_unit ? (float) $l->price_per_extra_unit : null,
                ]),
            ),
            'subscribers_count' => $this->when(
                $this->relationLoaded('storeSubscriptions'),
                fn () => $this->storeSubscriptions->count(),
            ),
            'created_at' => $this->created_at instanceof \DateTimeInterface
                ? $this->created_at->toIso8601String()
                : $this->created_at,
            'updated_at' => $this->updated_at instanceof \DateTimeInterface
                ? $this->updated_at->toIso8601String()
                : $this->updated_at,
        ];
    }
}
