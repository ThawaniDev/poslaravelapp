<?php

namespace Tests\Unit\Domain\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\PlanEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for PlanEnforcementService.
 *
 * Covers: feature enabled/disabled, canPerformAction, getRemainingQuota,
 * admin overrides, no-subscription handling, multiple features batch check,
 * unlimited limits (-1), and usage tracking.
 */
class PlanEnforcementServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private SubscriptionPlan $plan;
    private PlanEnforcementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Enforcement Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-enforcement',
            'monthly_price' => 29.99,
            'grace_period_days' => 7,
        ]);

        // Feature toggles
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'reports_advanced',
            'is_enabled' => false,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => true,
        ]);

        // Plan limits
        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'branches',
            'limit_value' => 3,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'products',
            'limit_value' => 100,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'staff_members',
            'limit_value' => -1, // unlimited
        ]);

        $this->service = app(PlanEnforcementService::class);
    }

    private function createActiveSubscription(): StoreSubscription
    {
        return StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    // ─── isFeatureEnabled ────────────────────────────────────────

    public function test_enabled_feature_returns_true(): void
    {
        $this->createActiveSubscription();

        $this->assertTrue($this->service->isFeatureEnabled($this->org->id, 'multi_branch'));
    }

    public function test_disabled_feature_returns_false(): void
    {
        $this->createActiveSubscription();

        $this->assertFalse($this->service->isFeatureEnabled($this->org->id, 'reports_advanced'));
    }

    public function test_nonexistent_feature_key_returns_false(): void
    {
        $this->createActiveSubscription();

        $this->assertFalse($this->service->isFeatureEnabled($this->org->id, 'nonexistent_feature'));
    }

    public function test_feature_check_returns_false_without_subscription(): void
    {
        // No subscription created
        $this->assertFalse($this->service->isFeatureEnabled($this->org->id, 'multi_branch'));
    }

    // ─── areFeaturesEnabled (Batch) ──────────────────────────────

    public function test_batch_feature_check_returns_correct_map(): void
    {
        $this->createActiveSubscription();

        $result = $this->service->areFeaturesEnabled($this->org->id, [
            'multi_branch',
            'reports_advanced',
            'delivery_integration',
        ]);

        $this->assertTrue($result['multi_branch']);
        $this->assertFalse($result['reports_advanced']);
        $this->assertTrue($result['delivery_integration']);
    }

    public function test_batch_feature_check_all_false_without_subscription(): void
    {
        $keys = ['multi_branch', 'reports_advanced', 'delivery_integration'];

        $result = $this->service->areFeaturesEnabled($this->org->id, $keys);

        foreach ($keys as $key) {
            $this->assertFalse($result[$key]);
        }
    }

    // ─── canPerformAction (Limit Check) ─────────────────────────

    public function test_can_perform_action_when_within_limit(): void
    {
        $this->createActiveSubscription();

        // branches limit is 3, we have 1 store (the main branch)
        $this->assertTrue($this->service->canPerformAction($this->org->id, 'branches'));
    }

    public function test_cannot_perform_action_when_limit_exceeded(): void
    {
        $this->createActiveSubscription();

        // Add 3 more branches to exceed the limit of 3
        Store::create(['organization_id' => $this->org->id, 'name' => 'B2', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => false]);
        Store::create(['organization_id' => $this->org->id, 'name' => 'B3', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => false]);
        Store::create(['organization_id' => $this->org->id, 'name' => 'B4', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => false]);

        // Now org has 4 branches but limit is 3
        $this->assertFalse($this->service->canPerformAction($this->org->id, 'branches'));
    }

    public function test_can_perform_action_returns_true_when_no_limit_configured(): void
    {
        $this->createActiveSubscription();

        // 'custom_limit_key' has no PlanLimit configured → unlimited
        $this->assertTrue($this->service->canPerformAction($this->org->id, 'unknown_resource'));
    }

    public function test_can_perform_action_returns_true_for_unlimited_limit(): void
    {
        $this->createActiveSubscription();

        // staff_members has limit_value = -1 (unlimited)
        $this->assertTrue($this->service->canPerformAction($this->org->id, 'staff_members'));
    }

    // ─── getRemainingQuota ───────────────────────────────────────

    public function test_remaining_quota_calculated_correctly(): void
    {
        $this->createActiveSubscription();

        // branches: limit = 3, current = 1 (main store)
        $remaining = $this->service->getRemainingQuota($this->org->id, 'branches');

        $this->assertSame(2, $remaining);
    }

    public function test_remaining_quota_is_null_for_unlimited_limit(): void
    {
        $this->createActiveSubscription();

        // staff_members is unlimited (-1)
        $remaining = $this->service->getRemainingQuota($this->org->id, 'staff_members');

        $this->assertNull($remaining);
    }

    public function test_remaining_quota_is_null_when_no_limit_configured(): void
    {
        $this->createActiveSubscription();

        $remaining = $this->service->getRemainingQuota($this->org->id, 'unregistered_key');

        $this->assertNull($remaining);
    }

    // ─── Admin Limit Override ────────────────────────────────────

    public function test_admin_override_takes_precedence_over_plan_limit(): void
    {
        $this->createActiveSubscription();

        // Plan limit: branches = 3
        // Admin override: branches = 10
        ProviderLimitOverride::create([
            'organization_id' => $this->org->id,
            'limit_key' => 'branches',
            'override_value' => 10,
            'reason' => 'Special deal',
        ]);

        $effective = $this->service->getEffectiveLimit($this->org->id, 'branches');

        $this->assertSame(10, $effective);
    }

    public function test_expired_admin_override_is_ignored(): void
    {
        $this->createActiveSubscription();

        ProviderLimitOverride::create([
            'organization_id' => $this->org->id,
            'limit_key' => 'branches',
            'override_value' => 20,
            'reason' => 'Old deal',
            'expires_at' => now()->subDay(), // expired
        ]);

        // Should fall back to plan limit: 3
        $effective = $this->service->getEffectiveLimit($this->org->id, 'branches');

        $this->assertSame(3, $effective);
    }

    public function test_admin_override_with_future_expiry_is_active(): void
    {
        $this->createActiveSubscription();

        ProviderLimitOverride::create([
            'organization_id' => $this->org->id,
            'limit_key' => 'branches',
            'override_value' => 15,
            'reason' => 'Active deal',
            'expires_at' => now()->addYear(),
        ]);

        $effective = $this->service->getEffectiveLimit($this->org->id, 'branches');

        $this->assertSame(15, $effective);
    }

    // ─── getEffectiveLimit ───────────────────────────────────────

    public function test_effective_limit_matches_plan_limit(): void
    {
        $this->createActiveSubscription();

        $this->assertSame(3, $this->service->getEffectiveLimit($this->org->id, 'branches'));
        $this->assertSame(100, $this->service->getEffectiveLimit($this->org->id, 'products'));
    }

    public function test_effective_limit_returns_null_for_no_plan_limit(): void
    {
        $this->createActiveSubscription();

        $this->assertNull($this->service->getEffectiveLimit($this->org->id, 'nonexistent_key'));
    }

    public function test_effective_limit_returns_null_for_unlimited(): void
    {
        $this->createActiveSubscription();

        // staff_members = -1 (unlimited) → returns null
        $this->assertNull($this->service->getEffectiveLimit($this->org->id, 'staff_members'));
    }

    // ─── getAllFeatureToggles ────────────────────────────────────

    public function test_get_all_feature_toggles_returns_all_keys(): void
    {
        $this->createActiveSubscription();

        $toggles = $this->service->getAllFeatureToggles($this->org->id);

        $this->assertCount(3, $toggles);

        $keys = array_column($toggles, 'feature_key');
        $this->assertContains('multi_branch', $keys);
        $this->assertContains('reports_advanced', $keys);
        $this->assertContains('delivery_integration', $keys);
    }

    public function test_get_all_feature_toggles_returns_empty_without_subscription(): void
    {
        $toggles = $this->service->getAllFeatureToggles($this->org->id);

        $this->assertEmpty($toggles);
    }

    // ─── Usage Tracking ─────────────────────────────────────────

    public function test_track_usage_creates_snapshot(): void
    {
        $this->createActiveSubscription();

        $snapshot = $this->service->trackUsage($this->org->id, 'products', 45);

        $this->assertSame('products', $snapshot->resource_type);
        $this->assertSame(45, (int) $snapshot->current_count);
    }

    public function test_increment_usage_adds_to_current(): void
    {
        $this->createActiveSubscription();

        $this->service->trackUsage($this->org->id, 'products', 40);
        $this->service->incrementUsage($this->org->id, 'products', 10);

        $current = $this->service->getCurrentUsage($this->org->id, 'products');

        $this->assertSame(50, $current);
    }

    public function test_track_usage_updates_existing_snapshot(): void
    {
        $this->createActiveSubscription();

        $this->service->trackUsage($this->org->id, 'products', 10);
        $this->service->trackUsage($this->org->id, 'products', 20); // should update, not duplicate

        $current = $this->service->getCurrentUsage($this->org->id, 'products');

        $this->assertSame(20, $current);
    }
}
