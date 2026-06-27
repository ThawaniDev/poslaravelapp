<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckActiveSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Enforcement tests for the POS "active subscription" gate.
 *
 * Verifies that creating a NEW online sale (POST /pos/transactions) is blocked
 * server-side when the organization has no active/trial/grace subscription —
 * the server-side mirror of the client POS paywall.
 *
 * NOTE: The base TestCase aliases `plan.active` to BypassPermissionMiddleware so
 * unrelated tests can focus on business logic. These tests restore the REAL
 * CheckActiveSubscription middleware, then put the bypass back in tearDown so
 * subsequent test classes are unaffected (the router is a singleton).
 */
class PosTransactionPlanActiveTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Restore the real plan.active enforcement for these tests.
        app('router')->aliasMiddleware('plan.active', CheckActiveSubscription::class);

        $this->org = Organization::create([
            'name' => 'Plan Active Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier_planactive@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        // Restore the bypass so other test classes are unaffected.
        app('router')->aliasMiddleware('plan.active', BypassPermissionMiddleware::class);

        parent::tearDown();
    }

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-planactive',
            'monthly_price' => 100.00,
            'annual_price' => 1000.00,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function subscribe(SubscriptionStatus $status): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->makePlan()->id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $status === SubscriptionStatus::Expired ? now()->subDay() : now()->addMonth(),
        ]);
    }

    private function salePayload(): array
    {
        return [
            'type' => 'sale',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'total_amount' => 115.00,
            'items' => [
                [
                    'product_name' => 'Coffee',
                    'quantity' => 2,
                    'unit_price' => 50.00,
                    'line_total' => 100.00,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 115.00,
                    'cash_tendered' => 120.00,
                    'change_given' => 5.00,
                ],
            ],
        ];
    }

    public function test_create_transaction_blocked_when_no_subscription(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'no_subscription')
            ->assertJsonPath('subscription_required', true);
    }

    public function test_create_transaction_blocked_when_subscription_expired(): void
    {
        $this->subscribe(SubscriptionStatus::Expired);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'no_subscription')
            ->assertJsonPath('subscription_required', true);
    }

    public function test_create_transaction_blocked_when_subscription_cancelled(): void
    {
        $this->subscribe(SubscriptionStatus::Cancelled);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(403)
            ->assertJsonPath('subscription_required', true);
    }

    public function test_create_transaction_allowed_when_subscription_active(): void
    {
        $this->subscribe(SubscriptionStatus::Active);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_create_transaction_allowed_when_subscription_grace(): void
    {
        $this->subscribe(SubscriptionStatus::Grace);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        // Grace period still allows selling (a banner nudges renewal client-side).
        $response->assertStatus(201);
    }
}
