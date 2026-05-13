<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use Illuminate\Support\Facades\DB;

class PlanEnforcementService
{
    /** @var array<string, StoreSubscription|null> Request-scoped subscription cache */
    private array $subscriptionCache = [];

    /**
     * Check whether a feature is enabled for the organization's current plan.
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
     * Check multiple features at once. Returns map of featureKey => bool.
     */
    public function areFeaturesEnabled(string $organizationId, array $featureKeys): array
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return array_fill_keys($featureKeys, false);
        }

        $toggles = PlanFeatureToggle::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->whereIn('feature_key', $featureKeys)
            ->pluck('is_enabled', 'feature_key')
            ->toArray();

        $result = [];
        foreach ($featureKeys as $key) {
            $result[$key] = $toggles[$key] ?? false;
        }

        return $result;
    }

    /**
     * Check whether the organization can perform an action that is subject to plan limits.
     */
    public function canPerformAction(string $organizationId, string $limitKey): bool
    {
        $remaining = $this->getRemainingQuota($organizationId, $limitKey);

        if ($remaining === null) {
            return true; // No limit configured: unlimited
        }

        return $remaining > 0;
    }

    /**
     * Get the remaining quota for a given limit key.
     */
    public function getRemainingQuota(string $organizationId, string $limitKey): ?int
    {
        $effectiveLimit = $this->getEffectiveLimit($organizationId, $limitKey);

        if ($effectiveLimit === null || $effectiveLimit === -1) {
            return null; // unlimited
        }

        $currentUsage = $this->getLiveUsageCount($organizationId, $limitKey);

        return max(0, $effectiveLimit - $currentUsage);
    }

    /**
     * Get the effective limit for an organization, considering plan limits and overrides.
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
            return null; // No subscription = unlimited (plan enforcement is handled at feature-access level)
        }

        $planLimit = PlanLimit::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->where('limit_key', $limitKey)
            ->first();

        if (! $planLimit) {
            return null; // No limit configured = unlimited
        }

        $value = (int) $planLimit->limit_value;

        // -1 means unlimited in the seeder
        return $value === -1 ? null : $value;
    }

    /**
     * Get LIVE usage count by actually counting resources in the database.
     * This is more accurate than snapshots for enforcement purposes.
     */
    public function getLiveUsageCount(string $organizationId, string $limitKey): int
    {
        $storeIds = Store::where('organization_id', $organizationId)->pluck('id');

        return match ($limitKey) {
            'products' => DB::table('products')
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count(),

            'staff_members' => DB::table('users')
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->count()
                + DB::table('staff_users')
                ->whereIn('store_id', $storeIds)
                ->where('status', 'active')
                ->count(),

            'cashier_terminals' => DB::table('registers')
                ->whereIn('store_id', $storeIds)
                ->where('is_active', true)
                ->count(),

            'branches' => Store::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->count(),

            'transactions_per_month' => DB::table('transactions')
                ->whereIn('store_id', $storeIds)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),

            'storage_mb' => $this->calculateStorageMb($organizationId),

            'pdf_reports_per_month' => DB::table('report_exports')
                ->where('organization_id', $organizationId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),

            default => $this->getCurrentUsage($organizationId, $limitKey),
        };
    }

    /**
     * Calculate storage usage in MB for an organization.
     */
    private function calculateStorageMb(string $organizationId): int
    {
        try {
            $storeIds = Store::where('organization_id', $organizationId)->pluck('id')->toArray();

            if (empty($storeIds)) {
                return 0;
            }

            // Cast model_id to text for UUID comparison (media.model_id may be bigint)
            $bytes = DB::table('media')
                ->whereIn(DB::raw('model_id::text'), $storeIds)
                ->sum('size');

            return (int) ceil($bytes / (1024 * 1024));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get current usage count from snapshot (for backward compatibility).
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
                'plan_limit' => $this->getEffectiveLimit($organizationId, $limitKey) ?? -1,
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
     * Refresh all usage snapshots for an organization using live counts.
     */
    public function refreshUsageSnapshots(string $organizationId): void
    {
        $subscription = $this->getActiveSubscription($organizationId);
        if (! $subscription) {
            return;
        }

        $limitKeys = ['products', 'staff_members', 'cashier_terminals', 'branches', 'transactions_per_month', 'storage_mb', 'pdf_reports_per_month'];

        foreach ($limitKeys as $key) {
            $this->trackUsage($organizationId, $key, $this->getLiveUsageCount($organizationId, $key));
        }
    }

    /**
     * Get a full usage summary for an organization.
     * Uses live counts for maximum accuracy.
     */
    public function getUsageSummary(string $organizationId): array
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return [];
        }

        // Batch load all plan limits
        $limits = PlanLimit::where('subscription_plan_id', $subscription->subscription_plan_id)->get();

        if ($limits->isEmpty()) {
            return [];
        }

        $limitKeys = $limits->pluck('limit_key')->toArray();

        // Batch load all active overrides
        $overrides = ProviderLimitOverride::where('organization_id', $organizationId)
            ->whereIn('limit_key', $limitKeys)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('limit_key');

        $summary = [];

        foreach ($limits as $limit) {
            $key = $limit->limit_key;

            // Effective limit: override takes precedence, then plan limit
            if ($overrides->has($key)) {
                $effective = (int) $overrides->get($key)->override_value;
            } else {
                $effective = (int) $limit->limit_value;
            }

            // -1 means unlimited
            $isUnlimited = $effective === -1;

            // Use live count for accuracy
            $current = $this->getLiveUsageCount($organizationId, $key);

            // Update snapshot as side effect
            $this->trackUsage($organizationId, $key, $current);

            $summary[] = [
                'limit_key' => $key,
                'current' => $current,
                'limit' => $isUnlimited ? -1 : $effective,
                'is_unlimited' => $isUnlimited,
                'remaining' => $isUnlimited ? -1 : max(0, $effective - $current),
                'percentage' => $isUnlimited ? 0 : ($effective > 0 ? round(($current / $effective) * 100, 1) : 0),
                'price_per_extra' => $limit->price_per_extra_unit,
            ];
        }

        return $summary;
    }

    /**
     * Get all feature toggles for an organization's plan.
     */
    public function getAllFeatureToggles(string $organizationId): array
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return [];
        }

        return PlanFeatureToggle::where('subscription_plan_id', $subscription->subscription_plan_id)
            ->get()
            ->map(fn ($t) => [
                'feature_key' => $t->feature_key,
                'name' => $t->name,
                'name_ar' => $t->name_ar,
                'is_enabled' => (bool) $t->is_enabled,
            ])
            ->toArray();
    }

    /**
     * Build a comprehensive entitlements payload for the Flutter app sync.
     */
    public function buildEntitlementsPayload(string $organizationId): array
    {
        $subscription = $this->getActiveSubscription($organizationId);

        if (! $subscription) {
            return [
                'has_subscription' => false,
                'status' => null,
                'plan_code' => null,
                'plan_name' => null,
                'plan_name_ar' => null,
                'features' => [],
                'limits' => [],
                'softpos' => null,
            ];
        }

        $plan = $subscription->subscriptionPlan;

        // Features
        $features = PlanFeatureToggle::where('subscription_plan_id', $plan->id)
            ->pluck('is_enabled', 'feature_key')
            ->map(fn ($v) => (bool) $v)
            ->toArray();

        // Limits with live usage (including admin overrides)
        $limits = [];
        $planLimits = PlanLimit::where('subscription_plan_id', $plan->id)->get();

        foreach ($planLimits as $pl) {
            $effectiveLimit = $this->getEffectiveLimit($organizationId, $pl->limit_key) ?? -1;
            $isUnlimited = $effectiveLimit === -1 || $effectiveLimit === null;
            $current = $this->getLiveUsageCount($organizationId, $pl->limit_key);

            $limits[$pl->limit_key] = [
                'limit' => $isUnlimited ? -1 : $effectiveLimit,
                'is_unlimited' => $isUnlimited,
                'current' => $current,
                'remaining' => $isUnlimited ? -1 : max(0, $effectiveLimit - $current),
                'percentage' => $isUnlimited ? 0 : ($effectiveLimit > 0 ? round(($current / $effectiveLimit) * 100, 1) : 0),
            ];
        }

        // SoftPOS info
        $softposInfo = null;
        if ($plan->softpos_free_eligible) {
            $thresholdCount      = (int) ($plan->softpos_free_threshold ?? 0);
            $thresholdAmount     = $plan->softpos_free_threshold_amount !== null
                ? (float) $plan->softpos_free_threshold_amount
                : null;
            $currentCount        = (int) $subscription->softpos_transaction_count;
            $currentSalesTotal   = (float) ($subscription->softpos_sales_total ?? 0);

            // Prefer amount-based progress when an amount threshold is configured.
            if ($thresholdAmount !== null && $thresholdAmount > 0) {
                $remaining  = max(0, $thresholdAmount - $currentSalesTotal);
                $percentage = round(($currentSalesTotal / $thresholdAmount) * 100, 1);
            } else {
                $remaining  = max(0, $thresholdCount - $currentCount);
                $percentage = $thresholdCount > 0
                    ? round(($currentCount / $thresholdCount) * 100, 1)
                    : 0;
            }

            $softposInfo = [
                'is_eligible'         => true,
                'threshold'           => $thresholdCount,
                'threshold_amount'    => $thresholdAmount,
                'threshold_period'    => $plan->softpos_free_threshold_period,
                'current_count'       => $currentCount,
                'current_sales_total' => $currentSalesTotal,
                'remaining'           => $remaining,
                'percentage'          => min(100, $percentage),
                'is_free'             => $subscription->is_softpos_free,
                'savings_amount'      => $subscription->is_softpos_free ? ($subscription->original_amount ?? 0) : 0,
            ];
        }

        $status = $subscription->status->value;
        $expiresAt = $subscription->current_period_end?->toIso8601String();
        $trialEndsAt = $subscription->trial_ends_at?->toIso8601String();
        $gracePeriodEndsAt = $subscription->status === SubscriptionStatus::Grace
            ? $expiresAt
            : null;

        return [
            'has_subscription' => true,
            // Flat top-level fields (preserved for backward compatibility with existing callers).
            'status' => $status,
            'plan_code' => $plan->slug,
            'plan_name' => $plan->name,
            'plan_name_ar' => $plan->name_ar,
            'plan_id' => $plan->id,
            'billing_cycle' => $subscription->billing_cycle?->value,
            'expires_at' => $expiresAt,
            'trial_ends_at' => $trialEndsAt,
            'grace_period_ends_at' => $gracePeriodEndsAt,
            'features' => $features,
            'limits' => $limits,
            'softpos' => $softposInfo,
            'is_softpos_free' => $subscription->is_softpos_free,
            // Nested objects consumed by the Flutter FeatureGateService cache.
            'subscription' => [
                'id' => $subscription->id,
                'status' => $status,
                'billing_cycle' => $subscription->billing_cycle?->value,
                'expires_at' => $expiresAt,
                'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                'current_period_end' => $expiresAt,
                'trial_ends_at' => $trialEndsAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                'is_softpos_free' => $subscription->is_softpos_free,
            ],
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'name_ar' => $plan->name_ar,
                'tier' => $plan->tier?->value,
                'monthly_price' => $plan->monthly_price,
                'annual_price' => $plan->annual_price,
                'softpos_free_eligible' => (bool) $plan->softpos_free_eligible,
            ],
        ];
    }

    /**
     * Get the active subscription for an organization (cached within request).
     */
    public function getActiveSubscription(string $organizationId): ?StoreSubscription
    {
        if (! array_key_exists($organizationId, $this->subscriptionCache)) {
            $this->subscriptionCache[$organizationId] = StoreSubscription::with('subscriptionPlan')
                ->where('organization_id', $organizationId)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trial->value,
                    SubscriptionStatus::Grace->value,
                ])
                ->first();
        }

        return $this->subscriptionCache[$organizationId];
    }

    /**
     * Mapping of feature keys to sidebar/route paths.
     * This is the master mapping used by both Laravel middleware and Flutter sidebar.
     */
    public static function featureRouteMapping(): array
    {
        return [
            // Core features (always enabled on all plans)
            'pos' => ['/pos', '/pos/terminals', '/pos/sessions', '/orders', '/transactions'],
            'zatca_phase2' => ['/zatca'],
            'inventory' => ['/inventory', '/products', '/categories', '/suppliers', '/labels', '/predefined-catalog'],
            'reports_basic' => ['/reports', '/reports/sales-summary', '/reports/hourly-sales', '/reports/product-performance', '/reports/category-breakdown', '/reports/payment-methods'],
            'barcode_scanning' => [],
            'cash_drawer' => ['/cash-management'],
            'customer_display' => [],
            'receipt_printing' => [],
            'offline_mode' => [],
            'mada_payments' => [],

            // Advanced features (plan-gated)
            'reports_advanced' => ['/reports/staff-performance', '/reports/inventory', '/reports/financial', '/reports/customers'],
            'multi_branch' => ['/branches'],
            'delivery_integration' => ['/delivery'],
            'customer_loyalty' => ['/promotions'],
            'api_access' => [],
            'white_label' => [],
            'priority_support' => [],
            'dedicated_manager' => [],
            'custom_integrations' => ['/thawani-integration'],
            'sla_guarantee' => [],

            // Industry verticals
            'industry_restaurant' => ['/industry/restaurant'],
            'industry_bakery' => ['/industry/bakery'],
            'industry_pharmacy' => ['/industry/pharmacy'],
            'industry_electronics' => ['/industry/electronics'],
            'industry_florist' => ['/industry/florist'],
            'industry_jewelry' => ['/industry/jewelry'],

            // AI & advanced tools
            'wameed_ai' => ['/wameed-ai', '/wameed-ai/suggestions', '/wameed-ai/usage', '/wameed-ai/billing', '/wameed-ai/settings'],
            'cashier_gamification' => ['/cashier-gamification', '/cashier-gamification/badges', '/cashier-gamification/anomalies', '/cashier-gamification/shift-reports', '/cashier-gamification/settings'],
            'pos_customization' => ['/customization', '/layout-templates', '/marketplace', '/receipt-templates', '/cfd-themes', '/label-layout-templates'],
            'companion_app' => ['/companion'],
            'installments' => ['/settings/installments'],
            'accounting' => ['/accounting'],
        ];
    }

    /**
     * Get the feature key that gates a specific route.
     */
    public static function getFeatureKeyForRoute(string $route): ?string
    {
        foreach (self::featureRouteMapping() as $featureKey => $routes) {
            if (in_array($route, $routes, true)) {
                return $featureKey;
            }
        }

        return null;
    }
}
