<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckActiveSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that the plan.active middleware correctly blocks inventory
 * endpoints when the organization has no active subscription.
 *
 * Scenarios:
 *  - No subscription → 403 with error_code=no_subscription
 *  - Expired subscription → 403
 *  - Active subscription → pass through (200/201)
 *  - Trial subscription → pass through (200/201)
 *  - Grace period subscription → pass through (200/201)
 *  - Cancelled subscription → 403
 */
class InventorySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $owner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Re-register real plan.active middleware (TestCase aliases it to bypass)
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->org = Organization::create([
            'name' => 'Subscription Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Sub Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@sub.test',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // No subscription
    // ═══════════════════════════════════════════════════════════

    public function test_no_subscription_returns_403(): void
    {
        // No subscription record exists
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'no_subscription');
    }

    public function test_no_subscription_blocks_all_inventory_endpoints(): void
    {
        $endpoints = [
            ['GET', '/api/v2/inventory/stock-levels'],
            ['GET', '/api/v2/inventory/goods-receipts'],
            ['GET', '/api/v2/inventory/stock-adjustments'],
            ['GET', '/api/v2/inventory/stock-transfers'],
            ['GET', '/api/v2/inventory/purchase-orders'],
            ['GET', '/api/v2/inventory/recipes'],
            ['GET', '/api/v2/inventory/stocktakes'],
            ['GET', '/api/v2/inventory/waste-records'],
            ['GET', '/api/v2/inventory/supplier-returns'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->withToken($this->token)->json($method, $url);
            $this->assertEquals(
                403,
                $response->status(),
                "Expected 403 for {$method} {$url} — no active subscription",
            );
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Expired subscription
    // ═══════════════════════════════════════════════════════════

    public function test_expired_subscription_returns_403(): void
    {
        $plan = $this->makePlan();

        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'expired',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subMonth(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'no_subscription');
    }

    public function test_cancelled_subscription_returns_403(): void
    {
        $plan = $this->makePlan();

        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(5),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // Valid subscription statuses that should pass
    // ═══════════════════════════════════════════════════════════

    public function test_active_subscription_allows_access(): void
    {
        $this->createSubscription('active');

        $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_trial_subscription_allows_access(): void
    {
        $this->createSubscription('trial');

        $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_grace_period_subscription_allows_access(): void
    {
        $this->createSubscription('grace');

        $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Active subscription passes through to controller
    // ═══════════════════════════════════════════════════════════

    public function test_active_subscription_passes_through_all_inventory_endpoints(): void
    {
        $this->createSubscription('active');

        $endpoints = [
            ['GET', '/api/v2/inventory/stock-levels'],
            ['GET', '/api/v2/inventory/goods-receipts'],
            ['GET', '/api/v2/inventory/stock-adjustments'],
            ['GET', '/api/v2/inventory/stock-transfers'],
            ['GET', '/api/v2/inventory/purchase-orders'],
            ['GET', '/api/v2/inventory/recipes'],
            ['GET', '/api/v2/inventory/stocktakes'],
            ['GET', '/api/v2/inventory/waste-records'],
            ['GET', '/api/v2/inventory/supplier-returns'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->withToken($this->token)->json($method, $url);
            $this->assertNotEquals(
                403,
                $response->status(),
                "Expected non-403 for {$method} {$url} with active subscription",
            );
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Subscription error response format
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_error_response_has_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels');

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'error_code',
                'subscription_required',
            ])
            ->assertJsonPath('success', false)
            ->assertJsonPath('subscription_required', true);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'sub-test-plan-' . uniqid(),
            'is_active' => true,
            'monthly_price' => 49.00,
            'annual_price' => 490.00,
        ]);
    }

    private function createSubscription(string $status): StoreSubscription
    {
        $plan = $this->makePlan();

        return StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }
}
