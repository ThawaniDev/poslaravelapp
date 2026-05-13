<?php

namespace Tests\Unit\Domain\ProviderSubscription;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Services\SoftPosService;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for SoftPosService — transaction recording, threshold logic, stats.
 */
class SoftPosServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private SubscriptionPlan $eligiblePlan;
    private SubscriptionPlan $ineligiblePlan;
    private StoreSubscription $subscription;
    private SoftPosService $softPos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'SoftPOS Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
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
            'softpos_free_threshold' => 5, // Low threshold for test speed
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $this->ineligiblePlan = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
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

        $this->softPos = app(SoftPosService::class);
    }

    // ─── recordTransaction ────────────────────────────────────────

    public function test_record_transaction_creates_softpos_record(): void
    {
        $txn = $this->softPos->recordTransaction(
            organizationId: $this->org->id,
            amount: 100.000,
        );

        $this->assertInstanceOf(SoftPosTransaction::class, $txn);
        $this->assertDatabaseHas('softpos_transactions', [
            'organization_id' => $this->org->id,
            'status' => 'completed',
        ]);
    }

    public function test_record_transaction_stores_all_fields(): void
    {
        $txn = $this->softPos->recordTransaction(
            organizationId: $this->org->id,
            amount: 250.500,
            storeId: $this->store->id,
            transactionRef: 'TXN-001',
            paymentMethod: 'mada',
            terminalId: null, // UUID not required at service level; terminal stamping tested separately
            metadata: ['source' => 'softpos'],
            platformFee: 1.500,
            gatewayFee: 0.750,
            margin: 0.750,
            feeType: 'percentage',
        );

        $this->assertEquals($this->store->id, $txn->store_id);
        $this->assertEquals('TXN-001', $txn->transaction_ref);
        $this->assertEquals('mada', $txn->payment_method);
        $this->assertNull($txn->terminal_id);
        $this->assertEquals('completed', $txn->status);
    }

    public function test_record_transaction_increments_counter(): void
    {
        $this->softPos->recordTransaction($this->org->id, 100.000);

        $this->assertEquals(1, $this->subscription->fresh()->softpos_transaction_count);
    }

    public function test_multiple_transactions_increment_counter(): void
    {
        $this->softPos->recordTransaction($this->org->id, 50.000);
        $this->softPos->recordTransaction($this->org->id, 75.000);
        $this->softPos->recordTransaction($this->org->id, 25.000);

        $this->assertEquals(3, $this->subscription->fresh()->softpos_transaction_count);
    }

    // ─── Threshold Logic ─────────────────────────────────────────

    public function test_threshold_reached_activates_softpos_free(): void
    {
        // Threshold is 5 in setup
        for ($i = 0; $i < 5; $i++) {
            $this->softPos->recordTransaction($this->org->id, 100.000);
        }

        $sub = $this->subscription->fresh();
        $this->assertTrue((bool) $sub->is_softpos_free);
        $this->assertEquals('softpos_threshold_reached', $sub->discount_reason);
        $this->assertNotNull($sub->original_amount);
        $this->assertEquals(49.99, (float) $sub->original_amount);
    }

    public function test_below_threshold_does_not_activate_free(): void
    {
        // Only 4 of 5 needed
        for ($i = 0; $i < 4; $i++) {
            $this->softPos->recordTransaction($this->org->id, 100.000);
        }

        $sub = $this->subscription->fresh();
        $this->assertFalse((bool) $sub->is_softpos_free);
        $this->assertNull($sub->discount_reason);
    }

    public function test_already_free_subscription_not_re_activated(): void
    {
        // Mark as already free
        $this->subscription->update([
            'is_softpos_free' => true,
            'softpos_transaction_count' => 5,
        ]);

        // Another transaction — early-return guard fires because is_softpos_free=true,
        // so the counter is NOT incremented (idempotent once free).
        $this->softPos->recordTransaction($this->org->id, 100.000);

        // Count stays at 5 — the guard prevented another increment
        $this->assertEquals(5, $this->subscription->fresh()->softpos_transaction_count);
        $this->assertTrue((bool) $this->subscription->fresh()->is_softpos_free);
    }

    public function test_ineligible_plan_does_not_increment_counter(): void
    {
        // Switch subscription to ineligible plan
        $this->subscription->update([
            'subscription_plan_id' => $this->ineligiblePlan->id,
        ]);

        $this->softPos->recordTransaction($this->org->id, 100.000);

        // Counter should stay at 0 — plan not eligible
        $this->assertEquals(0, $this->subscription->fresh()->softpos_transaction_count);
    }

    public function test_amount_threshold_activates_softpos_free(): void
    {
        $this->eligiblePlan->update([
            'softpos_free_threshold'        => null,
            'softpos_free_threshold_amount' => 500.000, // 500 SAR threshold
        ]);

        // 4 transactions × 100 SAR = 400 SAR — below threshold
        for ($i = 0; $i < 4; $i++) {
            $this->softPos->recordTransaction($this->org->id, 100.000);
        }
        $this->assertFalse((bool) $this->subscription->fresh()->is_softpos_free);

        // One more × 100 SAR = 500 SAR — at threshold
        $this->softPos->recordTransaction($this->org->id, 100.000);

        $this->assertTrue((bool) $this->subscription->fresh()->is_softpos_free);
    }

    public function test_zero_amount_threshold_does_not_activate_free(): void
    {
        $this->eligiblePlan->update([
            'softpos_free_threshold'        => null,
            'softpos_free_threshold_amount' => 0.000, // zero — must never trigger
        ]);

        // Any transaction should NOT activate free tier when threshold is 0
        $this->softPos->recordTransaction($this->org->id, 100.000);

        $this->assertFalse((bool) $this->subscription->fresh()->is_softpos_free);
    }

    public function test_zero_count_threshold_does_not_activate_free(): void
    {
        $this->eligiblePlan->update([
            'softpos_free_threshold'        => 0, // zero count — must never trigger
            'softpos_free_threshold_amount' => null,
        ]);

        $this->softPos->recordTransaction($this->org->id, 100.000);

        $this->assertFalse((bool) $this->subscription->fresh()->is_softpos_free);
    }

    public function test_no_subscription_does_not_error(): void
    {
        $orgWithNoSub = Organization::create([
            'name' => 'No Sub Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        // Should not throw — returns a transaction record for a non-subscribed org
        $txn = $this->softPos->recordTransaction($orgWithNoSub->id, 100.000);

        $this->assertInstanceOf(SoftPosTransaction::class, $txn);
    }

    // ─── getThresholdInfo ─────────────────────────────────────────

    public function test_get_threshold_info_returns_correct_structure(): void
    {
        $this->subscription->update(['softpos_transaction_count' => 3]);

        $info = $this->softPos->getThresholdInfo($this->org->id);

        $this->assertNotNull($info);
        $this->assertTrue($info['is_eligible']);
        $this->assertEquals(5, $info['threshold']);
        $this->assertEquals(3, $info['current_count']);
        $this->assertEquals(2, $info['remaining']);
        $this->assertEquals(60.0, $info['percentage']);
        $this->assertFalse($info['is_free']);
        $this->assertEquals(49.99, $info['subscription_amount']);
        $this->assertEquals(0, $info['savings_amount']);
    }

    public function test_get_threshold_info_shows_free_when_activated(): void
    {
        $this->subscription->update([
            'softpos_transaction_count' => 5,
            'is_softpos_free' => true,
        ]);

        $info = $this->softPos->getThresholdInfo($this->org->id);

        $this->assertTrue($info['is_free']);
        $this->assertEquals(49.99, $info['savings_amount']);
        $this->assertEquals(100.0, $info['percentage']);
        $this->assertEquals(0, $info['remaining']);
    }

    public function test_get_threshold_info_returns_null_for_ineligible_plan(): void
    {
        $this->subscription->update([
            'subscription_plan_id' => $this->ineligiblePlan->id,
        ]);

        $info = $this->softPos->getThresholdInfo($this->org->id);

        $this->assertNull($info);
    }

    public function test_get_threshold_info_returns_null_without_subscription(): void
    {
        $orgNoSub = Organization::create([
            'name' => 'No Sub',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $info = $this->softPos->getThresholdInfo($orgNoSub->id);

        $this->assertNull($info);
    }

    // ─── getStatistics ────────────────────────────────────────────

    public function test_get_statistics_returns_correct_structure(): void
    {
        $stats = $this->softPos->getStatistics($this->org->id);

        $this->assertArrayHasKey('total_transactions', $stats);
        $this->assertArrayHasKey('total_volume', $stats);
        $this->assertArrayHasKey('monthly_transactions', $stats);
        $this->assertArrayHasKey('monthly_volume', $stats);
        $this->assertArrayHasKey('threshold_info', $stats);
    }

    public function test_statistics_counts_completed_transactions(): void
    {
        $this->softPos->recordTransaction($this->org->id, 100.000);
        $this->softPos->recordTransaction($this->org->id, 200.000);

        $stats = $this->softPos->getStatistics($this->org->id);

        $this->assertEquals(2, $stats['total_transactions']);
        $this->assertEquals(300.0, $stats['total_volume']);
    }

    public function test_statistics_monthly_volume_is_correct(): void
    {
        $this->softPos->recordTransaction($this->org->id, 150.000);

        $stats = $this->softPos->getStatistics($this->org->id);

        $this->assertEquals(1, $stats['monthly_transactions']);
        $this->assertEquals(150.0, $stats['monthly_volume']);
    }

    public function test_statistics_empty_for_org_with_no_transactions(): void
    {
        $stats = $this->softPos->getStatistics($this->org->id);

        $this->assertEquals(0, $stats['total_transactions']);
        $this->assertEquals(0.0, $stats['total_volume']);
    }

    // ─── resetPeriodCounters ──────────────────────────────────────

    public function test_reset_period_counters_resets_eligible_subscriptions(): void
    {
        $this->subscription->update([
            'softpos_transaction_count' => 10,
            'is_softpos_free' => true,
            'softpos_count_reset_at' => now()->subMonth(),
        ]);

        $count = $this->softPos->resetPeriodCounters();

        $this->assertEquals(1, $count);

        $sub = $this->subscription->fresh();
        $this->assertEquals(0, $sub->softpos_transaction_count);
        // is_softpos_free is intentionally preserved by resetPeriodCounters() —
        // it is cleared by BillingService::renewPaidSubscriptions() to avoid
        // stripping the free-tier discount from the period invoice.
        $this->assertTrue((bool) $sub->is_softpos_free);
        $this->assertNull($sub->discount_reason);
    }

    public function test_reset_skips_ineligible_plan_subscriptions(): void
    {
        $this->subscription->update([
            'subscription_plan_id' => $this->ineligiblePlan->id,
            'softpos_transaction_count' => 10,
        ]);

        $count = $this->softPos->resetPeriodCounters();

        $this->assertEquals(0, $count);
    }

    public function test_reset_period_counters_includes_trial_subscriptions(): void
    {
        $this->subscription->update([
            'status' => SubscriptionStatus::Trial,
            'softpos_transaction_count' => 3,
            'softpos_count_reset_at' => now()->subMonth(),
        ]);

        $count = $this->softPos->resetPeriodCounters();

        $this->assertEquals(1, $count);
        $this->assertEquals(0, $this->subscription->fresh()->softpos_transaction_count);
    }

    public function test_reset_period_counters_includes_grace_subscriptions(): void
    {
        $this->subscription->update([
            'status' => SubscriptionStatus::Grace,
            'softpos_transaction_count' => 7,
            'softpos_count_reset_at' => now()->subMonth(),
        ]);

        $count = $this->softPos->resetPeriodCounters();

        $this->assertEquals(1, $count);
        $this->assertEquals(0, $this->subscription->fresh()->softpos_transaction_count);
    }

    // ─── getTransactionHistory ────────────────────────────────────

    public function test_get_transaction_history_returns_paginated_results(): void
    {
        $this->softPos->recordTransaction($this->org->id, 100.000);
        $this->softPos->recordTransaction($this->org->id, 200.000);

        $result = $this->softPos->getTransactionHistory($this->org->id, 10);

        $this->assertEquals(2, $result->total());
    }

    public function test_get_transaction_history_filters_by_date_range(): void
    {
        // Create a transaction in the past using DB to bypass model timestamp protection
        \Illuminate\Support\Facades\DB::table('softpos_transactions')->insert([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->org->id,
            'amount'          => 500.000,
            'status'          => 'completed',
            'created_at'      => now()->subMonths(2),
            'updated_at'      => now()->subMonths(2),
        ]);

        $this->softPos->recordTransaction($this->org->id, 100.000);

        // Filter to last month only
        $result = $this->softPos->getTransactionHistory(
            $this->org->id,
            perPage: 10,
            startDate: now()->subMonth()->toDateString(),
        );

        $this->assertEquals(1, $result->total());
    }
}
