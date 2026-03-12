<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $starterPlan;
    private SubscriptionPlan $growthPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Organization',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
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

        $this->starterPlan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 0,
            'annual_price' => 0,
            'trial_days' => 14,
            'grace_period_days' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->growthPlan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Features and limits for enforcement
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->starterPlan->id,
            'feature_key' => 'pos',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->starterPlan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => false,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->starterPlan->id,
            'limit_key' => 'products',
            'limit_value' => 50,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->starterPlan->id,
            'limit_key' => 'staff_members',
            'limit_value' => 2,
        ]);
    }

    // ─── Subscribe ───────────────────────────────────────────────

    public function test_can_subscribe_to_plan(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growthPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('store_subscriptions', [
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
        ]);
    }

    public function test_subscribe_with_trial_creates_trial_status(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->starterPlan->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'trial');

        $this->assertNotNull($response->json('data.trial_ends_at'));
    }

    public function test_subscribe_generates_invoice_for_paid_plan(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growthPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertDatabaseHas('invoices', [
            'amount' => 29.99,
        ]);
    }

    public function test_subscribe_skips_invoice_for_trial(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->starterPlan->id,
        ]);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_cannot_subscribe_twice(): void
    {
        // First subscription
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growthPlan->id,
        ]);

        // Second attempt
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->starterPlan->id,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_subscribe_validates_plan_id(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable();
    }

    public function test_subscribe_requires_auth(): void
    {
        $response = $this->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growthPlan->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_subscribe_with_yearly_billing(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growthPlan->id,
            'billing_cycle' => 'yearly',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.billing_cycle', 'yearly');

        $this->assertDatabaseHas('invoices', [
            'amount' => 299.99,
        ]);
    }

    // ─── Get Current ─────────────────────────────────────────────

    public function test_can_get_current_subscription(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_get_current_returns_null_when_no_subscription(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    // ─── Change Plan ─────────────────────────────────────────────

    public function test_can_change_plan(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->growthPlan->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subscription_plan_id', $this->growthPlan->id);
    }

    public function test_change_plan_fails_when_already_on_same_plan(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->growthPlan->id,
        ]);

        $response->assertStatus(409);
    }

    public function test_change_plan_fails_without_active_subscription(): void
    {
        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->growthPlan->id,
        ]);

        $response->assertNotFound();
    }

    // ─── Cancel ──────────────────────────────────────────────────

    public function test_can_cancel_subscription(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason' => 'Too expensive',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.cancelled_at'));
    }

    public function test_cancel_enters_grace_period(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel');

        // Growth plan has 7 grace days
        $response->assertOk()
            ->assertJsonPath('data.status', 'grace');
    }

    public function test_cancel_without_subscription_returns_404(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel');

        $response->assertNotFound();
    }

    // ─── Resume ──────────────────────────────────────────────────

    public function test_can_resume_cancelled_subscription(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now(),
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNull($response->json('data.cancelled_at'));
    }

    public function test_cannot_resume_active_subscription(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertNotFound();
    }

    // ─── Usage & Enforcement ─────────────────────────────────────

    public function test_can_check_feature_enabled(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // pos should be enabled
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-feature/pos');
        $response->assertOk()->assertJsonPath('data.is_enabled', true);

        // multi_branch should be disabled
        $response2 = $this->withToken($this->token)->getJson('/api/v2/subscription/check-feature/multi_branch');
        $response2->assertOk()->assertJsonPath('data.is_enabled', false);
    }

    public function test_can_check_limit_remaining(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-limit/products');

        $response->assertOk()
            ->assertJsonPath('data.limit_key', 'products')
            ->assertJsonPath('data.remaining', 50)
            ->assertJsonPath('data.can_perform', true);
    }

    public function test_can_get_usage_summary(): void
    {
        StoreSubscription::create([
            'store_id' => $this->store->id,
            'subscription_plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/usage');

        $response->assertOk();
        // Should have entries for each plan limit
        $this->assertIsArray($response->json('data'));
    }

    public function test_feature_check_returns_false_without_subscription(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-feature/pos');

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', false);
    }

    // ─── User without store ──────────────────────────────────────

    public function test_subscription_endpoints_fail_without_store(): void
    {
        $userNoStore = User::create([
            'name' => 'No Store User',
            'email' => 'nostore@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => null,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $tokenNoStore = $userNoStore->createToken('test', ['*'])->plainTextToken;

        $endpoints = [
            ['POST', '/api/v2/subscription/subscribe', ['plan_id' => $this->growthPlan->id]],
            ['GET', '/api/v2/subscription/current', []],
            ['GET', '/api/v2/subscription/usage', []],
            ['POST', '/api/v2/subscription/cancel', []],
            ['POST', '/api/v2/subscription/resume', []],
        ];

        foreach ($endpoints as [$method, $url, $data]) {
            $response = match ($method) {
                'GET' => $this->withToken($tokenNoStore)->getJson($url),
                'POST' => $this->withToken($tokenNoStore)->postJson($url, $data),
                default => $this->withToken($tokenNoStore)->getJson($url),
            };

            $response->assertStatus(404);
        }
    }
}
