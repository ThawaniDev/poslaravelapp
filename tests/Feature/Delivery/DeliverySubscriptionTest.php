<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests verifying subscription-level enforcement:
 *  - plan.feature:delivery_integration middleware blocks unauthenticated / no-feature users
 *  - max_delivery_platforms plan limit blocks creating too many configs
 *  - Upgrade path: deleting a config frees up a slot
 */
class DeliverySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // NOTE: TestCase bypasses plan.feature and plan.limit middleware by default.
        // Restore them for this test class so we can test the real middleware.
        $router = app('router');
        $router->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);
        $router->aliasMiddleware('plan.limit', \App\Http\Middleware\CheckPlanLimit::class);

        $this->org = Organization::create([
            'name' => 'Sub Org', 'business_type' => 'restaurant', 'country' => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Sub Branch', 'business_type' => 'restaurant',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $this->user = User::create([
            'name' => 'Owner', 'email' => 'sub@test.com',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner', 'is_active' => true,
        ]);

        DB::table('delivery_platforms')->updateOrInsert(
            ['slug' => 'jahez'],
            [
                'id' => DB::table('delivery_platforms')->where('slug', 'jahez')->value('id') ?? (string) Str::uuid(),
                'name' => 'Jahez', 'auth_method' => 'api_key', 'is_active' => true,
                'sort_order' => 1, 'default_commission_percent' => 18.5,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );
        DB::table('delivery_platforms')->updateOrInsert(
            ['slug' => 'marsool'],
            [
                'id' => DB::table('delivery_platforms')->where('slug', 'marsool')->value('id') ?? (string) Str::uuid(),
                'name' => 'Marsool', 'auth_method' => 'api_key', 'is_active' => true,
                'sort_order' => 2, 'default_commission_percent' => 15.0,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );
    }

    private function makeSubscription(int $maxPlatforms = 3, bool $deliveryEnabled = true): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro-sub',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => $deliveryEnabled,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $plan->id,
            'limit_key' => 'max_delivery_platforms',
            'limit_value' => $maxPlatforms,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active', 'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    private function headers(): array
    {
        $token = $this->user->createToken('t', ['*'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. No subscription — feature is locked
    // ─────────────────────────────────────────────────────────────────────

    public function test_delivery_routes_blocked_when_no_feature_access(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Free', 'slug' => 'free',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 0,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => false,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active', 'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $r = $this->getJson('/api/v2/delivery/configs', $this->headers());

        $r->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Feature enabled — routes accessible
    // ─────────────────────────────────────────────────────────────────────

    public function test_delivery_routes_accessible_with_feature_enabled(): void
    {
        $this->makeSubscription(maxPlatforms: 3, deliveryEnabled: true);

        $r = $this->getJson('/api/v2/delivery/configs', $this->headers());
        $r->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Plan limit blocks creating beyond max_delivery_platforms
    // ─────────────────────────────────────────────────────────────────────

    public function test_plan_limit_blocks_creating_too_many_configs(): void
    {
        $this->makeSubscription(maxPlatforms: 1);

        // Create 1 (allowed)
        $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez',
            'api_key'  => 'K1',
            'merchant_id' => 'M1',
        ], $this->headers())->assertOk();

        // Create 2nd (blocked)
        $r2 = $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'marsool',
            'api_key'  => 'K2',
            'merchant_id' => 'M2',
        ], $this->headers());

        $r2->assertStatus(403);
        $r2->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Updating an existing config at limit succeeds (not counted as new)
    // ─────────────────────────────────────────────────────────────────────

    public function test_updating_existing_config_at_limit_succeeds(): void
    {
        $this->makeSubscription(maxPlatforms: 1);
        $headers = $this->headers();

        // Create 1
        $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez', 'api_key' => 'K1', 'merchant_id' => 'M1',
        ], $headers)->assertOk();

        // Update the same platform
        $r = $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez', 'api_key' => 'K1-UPDATED', 'merchant_id' => 'M1',
        ], $headers);

        $r->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Plan limit = 0 blocks all creation
    // ─────────────────────────────────────────────────────────────────────

    public function test_plan_limit_zero_blocks_all_creation(): void
    {
        $this->makeSubscription(maxPlatforms: 0);

        $r = $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez', 'api_key' => 'K1', 'merchant_id' => 'M1',
        ], $this->headers());

        $r->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Deleting a config frees up a slot
    // ─────────────────────────────────────────────────────────────────────

    public function test_deleting_config_frees_a_slot(): void
    {
        $this->makeSubscription(maxPlatforms: 1);
        $headers = $this->headers();

        // Create 1
        $r1 = $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez', 'api_key' => 'K1', 'merchant_id' => 'M1',
        ], $headers)->assertOk();

        // Get the config id
        $configId = DeliveryPlatformConfig::where('store_id', $this->store->id)->first()->id;

        // Delete it
        $this->deleteJson("/api/v2/delivery/configs/{$configId}", [], $headers)->assertOk();

        // Now can create another (marsool already seeded in setUp, just create the config)
        $r2 = $this->postJson('/api/v2/delivery/configs', [
            'platform' => 'marsool', 'api_key' => 'K2', 'merchant_id' => 'M2',
        ], $headers);

        $r2->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. Unlimited plan (no limit row) allows any number of configs
    // ─────────────────────────────────────────────────────────────────────

    public function test_plan_with_no_limit_row_allows_many_configs(): void
    {
        // Create plan WITHOUT a max_delivery_platforms PlanLimit
        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise', 'slug' => 'ent',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 2,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => true,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active', 'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $platforms = [
            ['slug' => 'marsool', 'name' => 'Marsool'],
            ['slug' => 'hungerstation', 'name' => 'HungerStation'],
        ];
        foreach ($platforms as $p) {
            DB::table('delivery_platforms')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'name' => $p['name'], 'slug' => $p['slug'],
                'auth_method' => 'api_key', 'is_active' => true,
                'sort_order' => 1, 'default_commission_percent' => 15,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $headers = $this->headers();
        foreach (['jahez', 'marsool', 'hungerstation'] as $i => $platform) {
            $r = $this->postJson('/api/v2/delivery/configs', [
                'platform' => $platform,
                'api_key' => "KEY-{$i}",
                'merchant_id' => "MERCHANT-{$i}",
            ], $headers);
            $r->assertOk();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. Expired subscription blocks access
    // ─────────────────────────────────────────────────────────────────────

    public function test_expired_subscription_blocks_delivery_access(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Expired', 'slug' => 'expired',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => true,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'expired', 'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subMonth(),
        ]);

        $r = $this->getJson('/api/v2/delivery/configs', $this->headers());
        // Either 403 (feature blocked) or 402 (payment required) depending on impl.
        $this->assertContains($r->status(), [402, 403]);
    }
}
