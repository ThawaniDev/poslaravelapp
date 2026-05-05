<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the SoftPOS API endpoints.
 *
 * Covers:
 *  - GET  /api/v2/subscription/softpos/info
 *  - GET  /api/v2/subscription/softpos/statistics
 *  - POST /api/v2/subscription/softpos/record
 *  - GET  /api/v2/subscription/softpos/transactions
 */
class SoftPosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private Store $otherStore;
    private string $token;
    private SubscriptionPlan $eligiblePlan;
    private SubscriptionPlan $ineligiblePlan;
    private StoreSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'SoftPOS API Test Org',
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

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@softpos.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        // A second store in a different org (to test cross-org rejection)
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->eligiblePlan = SubscriptionPlan::create([
            'name' => 'SoftPOS Pro',
            'slug' => 'softpos-pro',
            'monthly_price' => 49.99,
            'annual_price' => 499.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
            'softpos_free_eligible' => true,
            'softpos_free_threshold' => 3, // low threshold for fast testing
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $this->ineligiblePlan = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic-plan',
            'monthly_price' => 9.99,
            'annual_price' => 99.99,
            'trial_days' => 0,
            'grace_period_days' => 0,
            'is_active' => true,
            'sort_order' => 2,
            'softpos_free_eligible' => false,
        ]);

        $this->subscription = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->eligiblePlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'softpos_transaction_count' => 0,
            'is_softpos_free' => false,
        ]);
    }

    // ─── GET /softpos/info ────────────────────────────────────────

    public function test_softpos_info_returns_threshold_for_eligible_plan(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/info');

        $response->assertOk()
            ->assertJsonPath('data.is_eligible', true)
            ->assertJsonPath('data.threshold', 3)
            ->assertJsonPath('data.current_count', 0)
            ->assertJsonPath('data.remaining', 3)
            ->assertJsonPath('data.is_free', false);
    }

    public function test_softpos_info_shows_not_eligible_for_ineligible_plan(): void
    {
        $this->subscription->update([
            'subscription_plan_id' => $this->ineligiblePlan->id,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/info');

        $response->assertOk()
            ->assertJsonPath('data.is_eligible', false);
    }

    public function test_softpos_info_shows_not_eligible_without_subscription(): void
    {
        $this->subscription->delete();

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/info');

        $response->assertOk()
            ->assertJsonPath('data.is_eligible', false);
    }

    public function test_softpos_info_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/softpos/info');
        $response->assertUnauthorized();
    }

    public function test_softpos_info_requires_organization(): void
    {
        $userNoOrg = User::create([
            'name' => 'No Org',
            'email' => 'noorg@softpos.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->withToken($userNoOrg->createToken('test', ['*'])->plainTextToken)
            ->getJson('/api/v2/subscription/softpos/info');

        $response->assertNotFound();
    }

    // ─── GET /softpos/statistics ──────────────────────────────────

    public function test_softpos_statistics_returns_correct_structure(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_transactions',
                    'total_volume',
                    'monthly_transactions',
                    'monthly_volume',
                    'threshold_info',
                ],
            ]);
    }

    public function test_softpos_statistics_initially_zero(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_transactions', 0);
        $this->assertEquals(0.0, (float) $response->json('data.total_volume'));
    }

    public function test_softpos_statistics_reflects_recorded_transactions(): void
    {
        // Record some transactions first
        $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', ['amount' => 100.000]);
        $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', ['amount' => 200.000]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_transactions', 2)
            ->assertJsonPath('data.monthly_transactions', 2);
    }

    public function test_softpos_statistics_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/softpos/statistics');
        $response->assertUnauthorized();
    }

    // ─── POST /softpos/record ─────────────────────────────────────

    public function test_record_softpos_transaction_succeeds(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
            'amount' => 150.500,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'threshold_info',
                ],
            ]);

        $this->assertDatabaseCount('softpos_transactions', 1);
    }

    public function test_record_softpos_transaction_with_all_optional_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
            'amount' => 99.999,
            'store_id' => $this->store->id,
            'transaction_ref' => 'TXN-ABC-123',
            'payment_method' => 'apple_pay',
            'terminal_id' => 'TERM-001',
            'metadata' => ['source' => 'softpos_sdk', 'version' => '2.0'],
        ]);

        $response->assertCreated();

        $txnId = $response->json('data.transaction_id');
        $this->assertDatabaseHas('softpos_transactions', [
            'id' => $txnId,
            'transaction_ref' => 'TXN-ABC-123',
            'payment_method' => 'apple_pay',
            'terminal_id' => 'TERM-001',
        ]);
    }

    public function test_record_softpos_validates_minimum_amount(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
            'amount' => 0, // below minimum of 0.001
        ]);

        $response->assertUnprocessable();
    }

    public function test_record_softpos_validates_amount_required(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_record_softpos_rejects_store_not_in_organization(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
            'amount' => 100.000,
            'store_id' => $this->otherStore->id, // different org
        ]);

        $response->assertForbidden();
    }

    public function test_record_softpos_updates_threshold_info_in_response(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
            'amount' => 100.000,
        ]);

        $response->assertCreated();
        $thresholdInfo = $response->json('data.threshold_info');

        $this->assertNotNull($thresholdInfo);
        $this->assertEquals(1, $thresholdInfo['current_count']);
        $this->assertEquals(3, $thresholdInfo['threshold']);
        $this->assertEquals(2, $thresholdInfo['remaining']);
    }

    public function test_record_softpos_requires_auth(): void
    {
        $response = $this->postJson('/api/v2/subscription/softpos/record', ['amount' => 100]);
        $response->assertUnauthorized();
    }

    public function test_record_softpos_requires_organization(): void
    {
        $userNoOrg = User::create([
            'name' => 'No Org User',
            'email' => 'noorgsoftpos@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->withToken($userNoOrg->createToken('test', ['*'])->plainTextToken)
            ->postJson('/api/v2/subscription/softpos/record', ['amount' => 100.000]);

        $response->assertNotFound();
    }

    // ─── Threshold Auto-Activation ────────────────────────────────

    public function test_softpos_free_activated_when_threshold_reached(): void
    {
        // Record 3 transactions (threshold = 3)
        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
                'amount' => 100.000,
            ]);
        }

        $this->subscription->refresh();
        $this->assertTrue((bool) $this->subscription->is_softpos_free);
        $this->assertEquals('softpos_threshold_reached', $this->subscription->discount_reason);

        // Verify the info endpoint reflects this
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/info');
        $response->assertOk()
            ->assertJsonPath('data.is_free', true);
        $this->assertEquals(100.0, (float) $response->json('data.percentage'));
    }

    public function test_softpos_free_not_activated_below_threshold(): void
    {
        // Record 2 of 3 needed
        for ($i = 0; $i < 2; $i++) {
            $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
                'amount' => 100.000,
            ]);
        }

        $this->subscription->refresh();
        $this->assertFalse((bool) $this->subscription->is_softpos_free);
    }

    // ─── GET /softpos/transactions ────────────────────────────────

    public function test_get_softpos_transactions_returns_paginated_list(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', ['amount' => 50.000]);
        $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', ['amount' => 75.000]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data',
                    'total',
                    'per_page',
                    'current_page',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_get_softpos_transactions_supports_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->token)->postJson('/api/v2/subscription/softpos/record', [
                'amount' => (float) (10 * ($i + 1)),
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/subscription/softpos/transactions?per_page=2');

        $response->assertOk();
        $this->assertEquals(5, $response->json('data.total'));
        $this->assertEquals(2, $response->json('data.per_page'));
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_get_softpos_transactions_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/softpos/transactions');
        $response->assertUnauthorized();
    }

    public function test_get_softpos_transactions_empty_for_new_org(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/softpos/transactions');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.total'));
    }
}
