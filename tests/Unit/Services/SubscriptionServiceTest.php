<?php

namespace Tests\Unit\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Enums\BillingCycle;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Services\PlanEnforcementService;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;
    private BillingService $billingService;
    private PlanEnforcementService $enforcementService;
    private Store $store;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = app(SubscriptionService::class);
        $this->billingService = app(BillingService::class);
        $this->enforcementService = app(PlanEnforcementService::class);

        $org = Organization::create([
            'name' => 'Test Org',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'test-' . Str::random(4),
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    // ─── SubscriptionService — Plan CRUD ─────────────────────────

    public function test_create_plan(): void
    {
        $plan = $this->subscriptionService->createPlan([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [
                ['feature_key' => 'pos', 'is_enabled' => true],
                ['feature_key' => 'api_access', 'is_enabled' => false],
            ],
            'limits' => [
                ['limit_key' => 'products', 'limit_value' => 50],
            ],
        ]);

        $this->assertInstanceOf(SubscriptionPlan::class, $plan);
        $this->assertEquals('starter', $plan->slug);
        $this->assertDatabaseHas('plan_feature_toggles', [
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'pos',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('plan_limits', [
            'subscription_plan_id' => $plan->id,
            'limit_key' => 'products',
            'limit_value' => 50,
        ]);
    }

    public function test_list_active_plans(): void
    {
        SubscriptionPlan::create([
            'name' => 'Active', 'slug' => 'active',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
        ]);
        SubscriptionPlan::create([
            'name' => 'Inactive', 'slug' => 'inactive',
            'monthly_price' => 10, 'is_active' => false, 'sort_order' => 2,
        ]);

        $plans = $this->subscriptionService->listPlans(true);
        $this->assertTrue($plans->every(fn($p) => $p->is_active));
    }

    public function test_get_plan_by_slug(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 1,
        ]);

        $found = $this->subscriptionService->getPlanBySlug('pro');
        $this->assertEquals($plan->id, $found->id);
    }

    public function test_compare_plans(): void
    {
        $p1 = SubscriptionPlan::create([
            'name' => 'Basic', 'slug' => 'basic',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 1,
        ]);
        $p2 = SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 2,
        ]);

        $comparison = $this->subscriptionService->comparePlans([$p1->id, $p2->id]);
        $this->assertCount(2, $comparison['plans']);
    }

    public function test_toggle_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Togglable', 'slug' => 'togglable',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
        ]);

        $toggled = $this->subscriptionService->togglePlan($plan);
        $this->assertFalse($toggled->is_active);

        $toggledBack = $this->subscriptionService->togglePlan($toggled);
        $this->assertTrue($toggledBack->is_active);
    }

    public function test_update_plan_syncs_features_and_limits(): void
    {
        $plan = $this->subscriptionService->createPlan([
            'name' => 'Updateable', 'slug' => 'updateable',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
            'features' => [['feature_key' => 'pos', 'is_enabled' => true]],
            'limits' => [['limit_key' => 'products', 'limit_value' => 50]],
        ]);

        $updated = $this->subscriptionService->updatePlan($plan, [
            'monthly_price' => 20,
            'features' => [
                ['feature_key' => 'pos', 'is_enabled' => true],
                ['feature_key' => 'api_access', 'is_enabled' => true],
            ],
            'limits' => [
                ['limit_key' => 'products', 'limit_value' => 100],
                ['limit_key' => 'staff_members', 'limit_value' => 10],
            ],
        ]);

        $this->assertEquals(20, $updated->monthly_price);
        $this->assertCount(2, $updated->planFeatureToggles);
        $this->assertCount(2, $updated->planLimits);
    }

    public function test_delete_plan_without_subscribers(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Delete Me', 'slug' => 'delete-me',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->subscriptionService->deletePlan($plan);
        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    // ─── BillingService — Subscription Lifecycle ─────────────────

    public function test_subscribe_to_free_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Free', 'slug' => 'free',
            'monthly_price' => 0, 'trial_days' => 0,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $subscription = $this->billingService->subscribe(
            $this->store->organization_id, $plan->id, BillingCycle::Monthly
        );

        $this->assertInstanceOf(StoreSubscription::class, $subscription);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_subscribe_with_trial(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Trial Plan', 'slug' => 'trial',
            'monthly_price' => 49, 'trial_days' => 14,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $subscription = $this->billingService->subscribe(
            $this->store->organization_id, $plan->id, BillingCycle::Monthly
        );

        $this->assertEquals(SubscriptionStatus::Trial, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    public function test_subscribe_yearly_extends_period(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Annual', 'slug' => 'annual',
            'monthly_price' => 49, 'annual_price' => 490,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $subscription = $this->billingService->subscribe(
            $this->store->organization_id, $plan->id, BillingCycle::Yearly
        );

        $periodLength = $subscription->current_period_start->diffInDays($subscription->current_period_end);
        $this->assertGreaterThanOrEqual(360, $periodLength);
    }

    public function test_cancel_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Cancellable', 'slug' => 'cancellable',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 1,
        ]);

        $subscription = $this->billingService->subscribe(
            $this->store->organization_id, $plan->id, BillingCycle::Monthly
        );

        $cancelled = $this->billingService->cancelSubscription($this->store->organization_id);

        $this->assertEquals(SubscriptionStatus::Grace, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_resume_cancelled_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Resumable', 'slug' => 'resumable',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->billingService->subscribe($this->store->organization_id, $plan->id, BillingCycle::Monthly);
        $this->billingService->cancelSubscription($this->store->organization_id);

        $resumed = $this->billingService->resumeSubscription($this->store->organization_id);

        $this->assertEquals(SubscriptionStatus::Active, $resumed->status);
        $this->assertNull($resumed->cancelled_at);
    }

    public function test_change_plan(): void
    {
        $plan1 = SubscriptionPlan::create([
            'name' => 'Basic', 'slug' => 'basic',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
        ]);
        $plan2 = SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->billingService->subscribe($this->store->organization_id, $plan1->id, BillingCycle::Monthly);

        $changed = $this->billingService->changePlan($this->store->organization_id, $plan2->id);

        $this->assertEquals($plan2->id, $changed->subscription_plan_id);
    }

    public function test_subscribe_generates_invoice(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Paid', 'slug' => 'paid',
            'monthly_price' => 49, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->billingService->subscribe($this->store->organization_id, $plan->id, BillingCycle::Monthly);

        $invoices = $this->billingService->getInvoices($this->store->organization_id);
        $this->assertGreaterThanOrEqual(1, $invoices->count());
    }

    public function test_get_current_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Current', 'slug' => 'current',
            'monthly_price' => 10, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->billingService->subscribe($this->store->organization_id, $plan->id, BillingCycle::Monthly);

        $current = $this->billingService->getCurrentSubscription($this->store->organization_id);
        $this->assertNotNull($current);
        $this->assertEquals($plan->id, $current->subscription_plan_id);
    }

    public function test_get_current_subscription_returns_null_when_none(): void
    {
        $current = $this->billingService->getCurrentSubscription($this->store->organization_id);
        $this->assertNull($current);
    }
}
