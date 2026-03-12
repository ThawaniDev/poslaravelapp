<?php

namespace App\Domain\Subscription\Services;

use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;

class PlanEnforcementService
{
    /**
     * Check whether a feature is enabled for the store's current plan.
     *
     * @return bool True if the feature is enabled.
     */
    public function isFeatureEnabled(string $storeId, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($storeId);

        if (! $subscription) {
            return false;
        }

        $toggle = PlanFeatureToggle::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->where('feature_key', $featureKey)
            ->first();

        return $toggle?->is_enabled ?? false;
    }

    /**
     * Check whether the store can perform an action that is subject to plan limits.
     *
     * @param  string  $limitKey  e.g. "products", "staff_members", "stores"
     * @return bool True if the action is allowed (still within quota).
     */
    public function canPerformAction(string $storeId, string $limitKey): bool
    {
        $remaining = $this->getRemainingQuota($storeId, $limitKey);

        if ($remaining === null) {
            // No limit configured: unlimited
            return true;
        }

        return $remaining > 0;
    }

    /**
     * Get the remaining quota for a given limit key.
     *
     * @return int|null Remaining count, or null if unlimited.
     */
    public function getRemainingQuota(string $storeId, string $limitKey): ?int
    {
        $effectiveLimit = $this->getEffectiveLimit($storeId, $limitKey);

        if ($effectiveLimit === null) {
            return null; // unlimited
        }

        $currentUsage = $this->getCurrentUsage($storeId, $limitKey);

        return max(0, $effectiveLimit - $currentUsage);
    }

    /**
     * Get the effective limit for a store, considering plan limits and overrides.
     *
     * @return int|null The effective limit value, or null if unlimited/no limit configured.
     */
    public function getEffectiveLimit(string $storeId, string $limitKey): ?int
    {
        // 1. Check for admin override (takes precedence)
        $override = ProviderLimitOverride::where('store_id', $storeId)
            ->where('limit_key', $limitKey)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($override) {
            return (int) $override->override_value;
        }

        // 2. Get plan limit
        $subscription = $this->getActiveSubscription($storeId);

        if (! $subscription) {
            return 0; // No subscription = no quota
        }

        $planLimit = PlanLimit::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->where('limit_key', $limitKey)
            ->first();

        if (! $planLimit) {
            return null; // No limit configured = unlimited
        }

        return (int) $planLimit->limit_value;
    }

    /**
     * Get current usage count for a resource.
     */
    public function getCurrentUsage(string $storeId, string $limitKey): int
    {
        $snapshot = SubscriptionUsageSnapshot::where('store_id', $storeId)
            ->where('resource_type', $limitKey)
            ->where('snapshot_date', today())
            ->first();

        return $snapshot ? (int) $snapshot->current_count : 0;
    }

    /**
     * Record or update usage for a resource.
     */
    public function trackUsage(string $storeId, string $limitKey, int $count): SubscriptionUsageSnapshot
    {
        return SubscriptionUsageSnapshot::updateOrCreate(
            [
                'store_id' => $storeId,
                'resource_type' => $limitKey,
                'snapshot_date' => today(),
            ],
            [
                'current_count' => $count,
                'plan_limit' => $this->getEffectiveLimit($storeId, $limitKey) ?? 0,
            ]
        );
    }

    /**
     * Increment usage for a resource by a given amount.
     */
    public function incrementUsage(string $storeId, string $limitKey, int $increment = 1): SubscriptionUsageSnapshot
    {
        $current = $this->getCurrentUsage($storeId, $limitKey);

        return $this->trackUsage($storeId, $limitKey, $current + $increment);
    }

    /**
     * Get a full usage summary for a store.
     *
     * @return array Array of { limit_key, current, limit, remaining, percentage }
     */
    public function getUsageSummary(string $storeId): array
    {
        $subscription = $this->getActiveSubscription($storeId);

        if (! $subscription) {
            return [];
        }

        $limits = PlanLimit::where('subscription_plan_id', $subscription->subscription_plan_id)->get();
        $summary = [];

        foreach ($limits as $limit) {
            $effective = $this->getEffectiveLimit($storeId, $limit->limit_key);
            $current = $this->getCurrentUsage($storeId, $limit->limit_key);

            $summary[] = [
                'limit_key' => $limit->limit_key,
                'current' => $current,
                'limit' => $effective,
                'remaining' => $effective !== null ? max(0, $effective - $current) : null,
                'percentage' => $effective && $effective > 0 ? round(($current / $effective) * 100, 1) : 0,
                'price_per_extra' => $limit->price_per_extra_unit,
            ];
        }

        return $summary;
    }

    /**
     * Get the active subscription for a store (cached within request).
     */
    private function getActiveSubscription(string $storeId): ?StoreSubscription
    {
        return StoreSubscription::where('store_id', $storeId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();
    }
}
