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
    /** @var array<string, StoreSubscription|null> Request-scoped subscription cache */
    private array $subscriptionCache = [];

    /**
     * Check whether a feature is enabled for the organization's current plan.
     *
     * @return bool True if the feature is enabled.
     */
    public function isFeatureEnabled(string $organizationId, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return false;
        }

        $toggle = PlanFeatureToggle::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->where('feature_key', $featureKey)
            ->first();

        return $toggle?->is_enabled ?? false;
    }

    /**
     * Check whether the organization can perform an action that is subject to plan limits.
     *
     * @param  string  $limitKey  e.g. "products", "staff_members", "stores"
     * @return bool True if the action is allowed (still within quota).
     */
    public function canPerformAction(string $organizationId, string $limitKey): bool
    {
        $remaining = $this->getRemainingQuota($organizationId, $limitKey);

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
    public function getRemainingQuota(string $organizationId, string $limitKey): ?int
    {
        $effectiveLimit = $this->getEffectiveLimit($organizationId, $limitKey);

        if ($effectiveLimit === null) {
            return null; // unlimited
        }

        $currentUsage = $this->getCurrentUsage($organizationId, $limitKey);

        return max(0, $effectiveLimit - $currentUsage);
    }

    /**
     * Get the effective limit for an organization, considering plan limits and overrides.
     *
     * @return int|null The effective limit value, or null if unlimited/no limit configured.
     */
    public function getEffectiveLimit(string $organizationId, string $limitKey): ?int
    {
        // 1. Check for admin override (takes precedence)
        $override = ProviderLimitOverride::where('organization_id', $organizationId)
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
        $subscription = $this->getActiveSubscription($organizationId);

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
    public function getCurrentUsage(string $organizationId, string $limitKey): int
    {
        $snapshot = SubscriptionUsageSnapshot::where('organization_id', $organizationId)
            ->where('resource_type', $limitKey)
            ->where('snapshot_date', today())
            ->first();

        return $snapshot ? (int) $snapshot->current_count : 0;
    }

    /**
     * Record or update usage for a resource.
     */
    public function trackUsage(string $organizationId, string $limitKey, int $count): SubscriptionUsageSnapshot
    {
        return SubscriptionUsageSnapshot::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'resource_type' => $limitKey,
                'snapshot_date' => today(),
            ],
            [
                'current_count' => $count,
                'plan_limit' => $this->getEffectiveLimit($organizationId, $limitKey) ?? 0,
            ]
        );
    }

    /**
     * Increment usage for a resource by a given amount.
     */
    public function incrementUsage(string $organizationId, string $limitKey, int $increment = 1): SubscriptionUsageSnapshot
    {
        $current = $this->getCurrentUsage($organizationId, $limitKey);

        return $this->trackUsage($organizationId, $limitKey, $current + $increment);
    }

    /**
     * Get a full usage summary for a store.
     * Optimized: batch-loads all overrides and snapshots in 3 queries total.
     *
     * @return array Array of { limit_key, current, limit, remaining, percentage }
     */
    public function getUsageSummary(string $organizationId): array
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return [];
        }

        // Batch load all plan limits (1 query)
        $limits = PlanLimit::where('subscription_plan_id', $subscription->subscription_plan_id)->get();

        if ($limits->isEmpty()) {
            return [];
        }

        $limitKeys = $limits->pluck('limit_key')->toArray();

        // Batch load all active overrides for this org (1 query)
        $overrides = ProviderLimitOverride::where('organization_id', $organizationId)
            ->whereIn('limit_key', $limitKeys)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('limit_key');

        // Batch load all today's usage snapshots (1 query)
        $snapshots = SubscriptionUsageSnapshot::where('organization_id', $organizationId)
            ->whereIn('resource_type', $limitKeys)
            ->where('snapshot_date', today())
            ->get()
            ->keyBy('resource_type');

        $summary = [];

        foreach ($limits as $limit) {
            $key = $limit->limit_key;

            // Effective limit: override takes precedence, then plan limit
            if ($overrides->has($key)) {
                $effective = (int) $overrides->get($key)->override_value;
            } else {
                $effective = (int) $limit->limit_value;
            }

            $current = $snapshots->has($key) ? (int) $snapshots->get($key)->current_count : 0;

            $summary[] = [
                'limit_key' => $key,
                'current' => $current,
                'limit' => $effective,
                'remaining' => max(0, $effective - $current),
                'percentage' => $effective > 0 ? round(($current / $effective) * 100, 1) : 0,
                'price_per_extra' => $limit->price_per_extra_unit,
            ];
        }

        return $summary;
    }

    /**
     * Get the active subscription for an organization (cached within request).
     */
    private function getActiveSubscription(string $organizationId): ?StoreSubscription
    {
        if (! array_key_exists($organizationId, $this->subscriptionCache)) {
            $this->subscriptionCache[$organizationId] = StoreSubscription::where('organization_id', $organizationId)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trial->value,
                    SubscriptionStatus::Grace->value,
                ])
                ->first();
        }

        return $this->subscriptionCache[$organizationId];
    }
}
