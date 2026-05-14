<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    // ─── Plan Queries ────────────────────────────────────────────

    /**
     * List subscription plans.
     *
     * @param bool        $activeOnly      Only return is_active plans.
     * @param string|null $businessType    Filter to this business_type (+ null-typed plans).
     * @param bool        $publicOnly      When true (default), exclude plans marked hide_from_public.
     *                                     Pass false for admin / authenticated contexts that should
     *                                     be able to see all plans (e.g. admin panel, upgrade flows).
     */
    public function listPlans(bool $activeOnly = true, ?string $businessType = null, bool $publicOnly = true): Collection
    {
        $query = SubscriptionPlan::with(['planFeatureToggles', 'planLimits', 'pricingPageContent'])
            ->orderBy('sort_order')
            ->orderBy('monthly_price');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        // Hide plans flagged as hide_from_public when listing for public/website contexts.
        if ($publicOnly) {
            $query->where(function ($q) {
                $q->where('hide_from_public', false)->orWhereNull('hide_from_public');
            });
        }

        if ($businessType) {
            $query->where(function ($q) use ($businessType) {
                $q->where('business_type', $businessType)
                    ->orWhereNull('business_type');
            });
        }

        return $query->get();
    }

    /**
     * Get a single plan with all relations.
     */
    public function getPlan(string $planId): SubscriptionPlan
    {
        return SubscriptionPlan::with(['planFeatureToggles', 'planLimits', 'pricingPageContent'])
            ->findOrFail($planId);
    }

    /**
     * Get plan by slug.
     */
    public function getPlanBySlug(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::with(['planFeatureToggles', 'planLimits', 'pricingPageContent'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Compare multiple plans side by side.
     *
     * @return array{plans: Collection, features: array, limits: array}
     */
    public function comparePlans(array $planIds = []): array
    {
        $query = SubscriptionPlan::with(['planFeatureToggles', 'planLimits', 'pricingPageContent'])
            ->where('is_active', true)
            ->orderBy('sort_order');

        if (! empty($planIds)) {
            $query->whereIn('id', $planIds);
        }

        $plans = $query->get();

        // Collect all feature keys across plans
        $allFeatureKeys = $plans->flatMap(fn ($plan) =>
            $plan->planFeatureToggles->pluck('feature_key')
        )->unique()->sort()->values()->all();

        // Collect all limit keys across plans
        $allLimitKeys = $plans->flatMap(fn ($plan) =>
            $plan->planLimits->pluck('limit_key')
        )->unique()->sort()->values()->all();

        // Build comparison matrix
        $features = [];
        foreach ($allFeatureKeys as $featureKey) {
            $features[$featureKey] = [];
            foreach ($plans as $plan) {
                $toggle = $plan->planFeatureToggles->firstWhere('feature_key', $featureKey);
                $features[$featureKey][$plan->id] = $toggle ? $toggle->is_enabled : false;
            }
        }

        $limits = [];
        foreach ($allLimitKeys as $limitKey) {
            $limits[$limitKey] = [];
            foreach ($plans as $plan) {
                $limit = $plan->planLimits->firstWhere('limit_key', $limitKey);
                $limits[$limitKey][$plan->id] = $limit ? $limit->limit_value : null;
            }
        }

        return [
            'plans' => $plans,
            'features' => $features,
            'limits' => $limits,
        ];
    }

    // ─── Plan CRUD (Admin) ───────────────────────────────────────

    /**
     * Create a new subscription plan.
     */
    public function createPlan(array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($data) {
            $features = $data['features'] ?? [];
            $limits = $data['limits'] ?? [];
            unset($data['features'], $data['limits']);

            $plan = SubscriptionPlan::create($data);

            foreach ($features as $feature) {
                PlanFeatureToggle::create([
                    'subscription_plan_id' => $plan->id,
                    'feature_key' => $feature['feature_key'],
                    'is_enabled' => $feature['is_enabled'] ?? true,
                ]);
            }

            foreach ($limits as $limit) {
                PlanLimit::create([
                    'subscription_plan_id' => $plan->id,
                    'limit_key' => $limit['limit_key'],
                    'limit_value' => $limit['limit_value'],
                    'price_per_extra_unit' => $limit['price_per_extra_unit'] ?? null,
                ]);
            }

            return $plan->load(['planFeatureToggles', 'planLimits']);
        });
    }

    /**
     * Update an existing subscription plan.
     */
    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $features = $data['features'] ?? null;
            $limits = $data['limits'] ?? null;
            unset($data['features'], $data['limits']);

            if (! empty($data)) {
                $plan->update($data);
            }

            // Sync features if provided
            if ($features !== null) {
                $plan->planFeatureToggles()->delete();
                foreach ($features as $feature) {
                    PlanFeatureToggle::create([
                        'subscription_plan_id' => $plan->id,
                        'feature_key' => $feature['feature_key'],
                        'is_enabled' => $feature['is_enabled'] ?? true,
                    ]);
                }
            }

            // Sync limits if provided
            if ($limits !== null) {
                $plan->planLimits()->delete();
                foreach ($limits as $limit) {
                    PlanLimit::create([
                        'subscription_plan_id' => $plan->id,
                        'limit_key' => $limit['limit_key'],
                        'limit_value' => $limit['limit_value'],
                        'price_per_extra_unit' => $limit['price_per_extra_unit'] ?? null,
                    ]);
                }
            }

            return $plan->fresh(['planFeatureToggles', 'planLimits']);
        });
    }

    /**
     * Toggle plan active status.
     */
    public function togglePlan(SubscriptionPlan $plan): SubscriptionPlan
    {
        $plan->update(['is_active' => ! $plan->is_active]);
        return $plan->fresh();
    }

    /**
     * Delete a plan (soft check: can't delete if subscribers exist).
     */
    public function deletePlan(SubscriptionPlan $plan): void
    {
        // Check for active subscriptions
        $activeSubscribers = $plan->storeSubscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->count();

        if ($activeSubscribers > 0) {
            throw new \RuntimeException(
                "Cannot delete plan '{$plan->name}': {$activeSubscribers} active subscriber(s). Deactivate it instead."
            );
        }

        DB::transaction(function () use ($plan) {
            $plan->planFeatureToggles()->delete();
            $plan->planLimits()->delete();
            $plan->delete();
        });
    }

    // ─── Add-ons ─────────────────────────────────────────────────

    /**
     * List all available add-ons.
     */
    public function listAddOns(bool $activeOnly = true): Collection
    {
        $query = PlanAddOn::orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}
