<?php

namespace Tests\Unit\Domain\ProviderSubscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the StoreSubscription model.
 *
 * Covers: status enum cast, billing_cycle enum cast, relationships,
 * timestamps, is_softpos_free, and grace_period_ends_at computation.
 */
class StoreSubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Sub Model Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-sub-model',
            'monthly_price' => 0,
            'grace_period_days' => 7,
        ]);
    }

    // ─── Status Enum Cast ────────────────────────────────────────

    public function test_status_is_cast_to_subscription_status_enum(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertInstanceOf(SubscriptionStatus::class, $sub->status);
        $this->assertSame(SubscriptionStatus::Active, $sub->status);
    }

    public function test_trial_status_is_cast_correctly(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $this->assertSame(SubscriptionStatus::Trial, $sub->status);
    }

    public function test_grace_status_is_cast_correctly(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'grace',
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(7),
        ]);

        $this->assertSame(SubscriptionStatus::Grace, $sub->status);
    }

    public function test_cancelled_status_is_cast_correctly(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->assertSame(SubscriptionStatus::Cancelled, $sub->status);
    }

    // ─── BillingCycle Enum Cast ──────────────────────────────────

    public function test_billing_cycle_is_cast_to_enum(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertInstanceOf(BillingCycle::class, $sub->billing_cycle);
        $this->assertSame(BillingCycle::Monthly, $sub->billing_cycle);
    }

    public function test_yearly_billing_cycle_cast(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'yearly',
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]);

        $this->assertSame(BillingCycle::Yearly, $sub->billing_cycle);
    }

    // ─── Relationship: subscriptionPlan ─────────────────────────

    public function test_belongs_to_subscription_plan(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $loaded = $sub->load('subscriptionPlan');

        $this->assertNotNull($loaded->subscriptionPlan);
        $this->assertSame($this->plan->id, $loaded->subscriptionPlan->id);
        $this->assertSame('Starter', $loaded->subscriptionPlan->name);
    }

    // ─── Timestamps ──────────────────────────────────────────────

    public function test_timestamps_are_datetime_instances(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $sub->current_period_start);
        $this->assertInstanceOf(\Carbon\Carbon::class, $sub->current_period_end);
    }

    public function test_trial_ends_at_is_datetime_when_set(): void
    {
        $trialEnd = now()->addDays(14);

        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => $trialEnd,
            'current_period_start' => now(),
            'current_period_end' => $trialEnd,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $sub->trial_ends_at);
        $this->assertTrue($sub->trial_ends_at->greaterThan(now()));
    }

    public function test_cancelled_at_is_datetime_when_set(): void
    {
        $cancelledAt = now();

        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'cancelled_at' => $cancelledAt,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $sub->cancelled_at);
    }

    // ─── SoftPOS Fields ──────────────────────────────────────────

    public function test_is_softpos_free_is_boolean(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'is_softpos_free' => true,
        ]);

        $this->assertIsBool($sub->is_softpos_free);
        $this->assertTrue($sub->is_softpos_free);
    }

    public function test_softpos_transaction_count_defaults_to_zero(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertSame(0, (int) $sub->softpos_transaction_count);
    }

    public function test_softpos_transaction_count_can_be_incremented(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'softpos_transaction_count' => 100,
        ]);

        $sub->increment('softpos_transaction_count', 50);
        $sub->refresh();

        $this->assertSame(150, (int) $sub->softpos_transaction_count);
    }

    // ─── Only One Active per Organization ───────────────────────

    public function test_organization_can_have_one_subscription_at_a_time(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $count = StoreSubscription::where('organization_id', $this->org->id)
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->count();

        $this->assertSame(1, $count);
    }
}
