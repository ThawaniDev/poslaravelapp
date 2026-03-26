<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\PlanEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;
    private PlanEnforcementService $enforcement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Organization',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Features
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'pos',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => false,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'api_access',
            'is_enabled' => true,
        ]);

        // Limits
        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'products',
            'limit_value' => 50,
            'price_per_extra_unit' => 0.10,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->plan->id,
            'limit_key' => 'staff',
            'limit_value' => 2,
        ]);

        $this->subscription = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->enforcement = app(PlanEnforcementService::class);
    }

    // ─── Feature Checks (Service Layer) ──────────────────────────

    public function test_enabled_feature_returns_true(): void
    {
        $this->assertTrue($this->enforcement->isFeatureEnabled($this->org->id, 'pos'));
    }

    public function test_disabled_feature_returns_false(): void
    {
        $this->assertFalse($this->enforcement->isFeatureEnabled($this->org->id, 'multi_branch'));
    }

    public function test_unknown_feature_returns_false(): void
    {
        $this->assertFalse($this->enforcement->isFeatureEnabled($this->org->id, 'nonexistent_feature'));
    }

    public function test_feature_check_returns_false_without_subscription(): void
    {
        $this->subscription->delete();
        $this->assertFalse($this->enforcement->isFeatureEnabled($this->org->id, 'pos'));
    }

    // ─── Limit Checks (Service Layer) ────────────────────────────

    public function test_can_perform_action_within_limit(): void
    {
        $this->assertTrue($this->enforcement->canPerformAction($this->org->id, 'products'));
    }

    public function test_cannot_perform_action_at_limit(): void
    {
        // Record usage at the limit
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 50,
            'plan_limit' => 50,
            'snapshot_date' => today(),
        ]);

        $this->assertFalse($this->enforcement->canPerformAction($this->org->id, 'products'));
    }

    public function test_cannot_perform_action_over_limit(): void
    {
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'staff',
            'current_count' => 5,
            'plan_limit' => 2,
            'snapshot_date' => today(),
        ]);

        $this->assertFalse($this->enforcement->canPerformAction($this->org->id, 'staff'));
    }

    public function test_unconfigured_limit_allows_action(): void
    {
        // 'branches' has no limit configured — should be unlimited
        $this->assertTrue($this->enforcement->canPerformAction($this->org->id, 'branches'));
    }

    public function test_remaining_quota_calculated_correctly(): void
    {
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 35,
            'plan_limit' => 50,
            'snapshot_date' => today(),
        ]);

        $remaining = $this->enforcement->getRemainingQuota($this->org->id, 'products');
        $this->assertEquals(15, $remaining);
    }

    public function test_remaining_quota_is_null_for_unlimited(): void
    {
        $remaining = $this->enforcement->getRemainingQuota($this->org->id, 'branches');
        $this->assertNull($remaining);
    }

    public function test_remaining_quota_never_negative(): void
    {
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 100,
            'plan_limit' => 50,
            'snapshot_date' => today(),
        ]);

        $remaining = $this->enforcement->getRemainingQuota($this->org->id, 'products');
        $this->assertEquals(0, $remaining);
    }

    // ─── Admin Overrides ─────────────────────────────────────────

    public function test_admin_override_takes_precedence(): void
    {
        ProviderLimitOverride::create([
            'organization_id' => $this->org->id,
            'limit_key' => 'products',
            'override_value' => 500,
            'reason' => 'Special deal',
            'set_by' => $this->owner->id, // Using owner as proxy
        ]);

        $effective = $this->enforcement->getEffectiveLimit($this->org->id, 'products');
        $this->assertEquals(500, $effective);
    }

    public function test_expired_override_is_ignored(): void
    {
        ProviderLimitOverride::create([
            'organization_id' => $this->org->id,
            'limit_key' => 'products',
            'override_value' => 500,
            'reason' => 'Expired deal',
            'set_by' => $this->owner->id,
            'expires_at' => now()->subDay(),
        ]);

        $effective = $this->enforcement->getEffectiveLimit($this->org->id, 'products');
        $this->assertEquals(50, $effective); // Falls back to plan limit
    }

    // ─── Usage Tracking ──────────────────────────────────────────

    public function test_track_usage_creates_snapshot(): void
    {
        $this->enforcement->trackUsage($this->org->id, 'products', 10);

        $this->assertDatabaseHas('subscription_usage_snapshots', [
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 10,
        ]);
    }

    public function test_track_usage_updates_existing_snapshot(): void
    {
        $this->enforcement->trackUsage($this->org->id, 'products', 10);
        $this->enforcement->trackUsage($this->org->id, 'products', 20);

        $count = SubscriptionUsageSnapshot::where('organization_id', $this->org->id)
            ->where('resource_type', 'products')
            ->where('snapshot_date', today())
            ->count();

        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('subscription_usage_snapshots', [
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 20,
        ]);
    }

    public function test_increment_usage(): void
    {
        $this->enforcement->trackUsage($this->org->id, 'products', 10);
        $this->enforcement->incrementUsage($this->org->id, 'products', 5);

        $this->assertDatabaseHas('subscription_usage_snapshots', [
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 15,
        ]);
    }

    // ─── Usage Summary ───────────────────────────────────────────

    public function test_usage_summary_includes_all_plan_limits(): void
    {
        $summary = $this->enforcement->getUsageSummary($this->org->id);
        $keys = array_column($summary, 'limit_key');
        $this->assertContains('products', $keys);
        $this->assertContains('staff', $keys);
    }

    public function test_usage_summary_calculates_percentage(): void
    {
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 25,
            'plan_limit' => 50,
            'snapshot_date' => today(),
        ]);

        $summary = $this->enforcement->getUsageSummary($this->org->id);
        $products = collect($summary)->firstWhere('limit_key', 'products');

        $this->assertEquals(25, $products['current']);
        $this->assertEquals(50, $products['limit']);
        $this->assertEquals(25, $products['remaining']);
        $this->assertEquals(50.0, $products['percentage']);
    }

    public function test_usage_summary_empty_without_subscription(): void
    {
        $this->subscription->delete();
        $summary = $this->enforcement->getUsageSummary($this->org->id);
        $this->assertEmpty($summary);
    }

    // ─── API Endpoint Tests ──────────────────────────────────────

    public function test_api_check_feature_enabled(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/check-feature/pos');

        $response->assertOk()
            ->assertJsonPath('data.feature_key', 'pos')
            ->assertJsonPath('data.is_enabled', true);
    }

    public function test_api_check_feature_disabled(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/check-feature/multi_branch');

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', false);
    }

    public function test_api_check_limit_within_quota(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/check-limit/products');

        $response->assertOk()
            ->assertJsonPath('data.limit_key', 'products')
            ->assertJsonPath('data.can_perform', true);
    }

    public function test_api_check_limit_exceeded(): void
    {
        SubscriptionUsageSnapshot::create([
            'organization_id' => $this->org->id,
            'resource_type' => 'products',
            'current_count' => 50,
            'plan_limit' => 50,
            'snapshot_date' => today(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/check-limit/products');

        $response->assertOk()
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.can_perform', false);
    }

    public function test_api_usage_summary(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/usage');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_check_feature(): void
    {
        $response = $this->getJson('/api/v2/subscription/check-feature/pos');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_check_limit(): void
    {
        $response = $this->getJson('/api/v2/subscription/check-limit/products');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_get_usage(): void
    {
        $response = $this->getJson('/api/v2/subscription/usage');
        $response->assertUnauthorized();
    }
}
