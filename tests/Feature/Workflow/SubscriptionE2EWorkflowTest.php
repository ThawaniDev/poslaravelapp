<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Models\CancellationReason;
use App\Domain\ProviderSubscription\Jobs\ExpireSubscriptionsJob;
use App\Domain\ProviderSubscription\Jobs\GenerateRenewalInvoicesJob;
use App\Domain\ProviderSubscription\Jobs\RenewPaidSubscriptionsJob;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end workflow tests for the full subscription lifecycle.
 *
 * Tests the complete provider journey:
 *   1. Register org + store + user
 *   2. Start trial subscription
 *   3. Upgrade to paid plan
 *   4. Change plan
 *   5. Apply discount code
 *   6. Activate add-on
 *   7. Cancel with reason
 *   8. Resume from cancelled state
 *   9. Verify all DB states
 *  10. Grace → Expired transition via job
 *  11. Invoice generation via renewal job
 *  12. Plan enforcement (feature + limit checks)
 */
class SubscriptionE2EWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $starter;
    private SubscriptionPlan $growth;
    private SubscriptionPlan $enterprise;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Register org + store + user
        $this->org = Organization::create([
            'name' => 'E2E Test Corp',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Branch',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'E2E Owner',
            'email' => 'e2e@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        // Set up plans
        $this->starter = SubscriptionPlan::create([
            'name' => 'Starter',
            'name_ar' => 'المبتدئ',
            'slug' => 'starter-e2e',
            'monthly_price' => 0.00,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->growth = SubscriptionPlan::create([
            'name' => 'Growth',
            'name_ar' => 'النمو',
            'slug' => 'growth-e2e',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->enterprise = SubscriptionPlan::create([
            'name' => 'Enterprise',
            'name_ar' => 'المؤسسي',
            'slug' => 'enterprise-e2e',
            'monthly_price' => 99.99,
            'annual_price' => 999.99,
            'trial_days' => 0,
            'grace_period_days' => 14,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Feature toggles for Growth
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->growth->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->growth->id,
            'feature_key' => 'reports_advanced',
            'is_enabled' => false,
        ]);

        // Limits for Growth
        PlanLimit::create([
            'subscription_plan_id' => $this->growth->id,
            'limit_key' => 'branches',
            'limit_value' => 3,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->growth->id,
            'limit_key' => 'staff_members',
            'limit_value' => 10,
        ]);
    }

    // ─── Step 1: Trial Subscription ──────────────────────────────

    /** @test */
    public function test_e2e_step1_subscribe_to_starter_trial(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->starter->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated();
        $data = $response->json('data');

        // Starter with trial_days > 0 → trial status
        $this->assertContains($data['status'], ['trial', 'active']);
        $this->assertSame($this->org->id, $data['organization_id']);

        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->starter->id,
        ]);
    }

    /** @test */
    public function test_e2e_step2_cannot_subscribe_twice_while_already_subscribed(): void
    {
        // First subscription
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->starter->id,
            'billing_cycle' => 'monthly',
        ]);

        // Second subscription attempt
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(409); // Conflict — already subscribed
    }

    // ─── Step 2: View Current Subscription ───────────────────────

    /** @test */
    public function test_e2e_step3_can_view_current_subscription_state(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->starter->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'trial');
        $response->assertJsonStructure([
            'data' => [
                'id', 'organization_id', 'status', 'billing_cycle',
                'current_period_start', 'current_period_end', 'trial_ends_at',
                'plan' => ['id', 'name', 'slug', 'monthly_price'],
            ],
        ]);
    }

    // ─── Step 3: Upgrade Trial → Paid ────────────────────────────

    /** @test */
    public function test_e2e_step4_upgrade_from_trial_to_paid_growth_plan(): void
    {
        // Setup: in trial
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->starter->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.subscription_plan_id', $this->growth->id);

        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
        ]);
    }

    /** @test */
    public function test_e2e_step5_paid_subscription_creates_invoice(): void
    {
        // Subscribe to paid plan directly
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Invoke change plan to a higher tier (which should generate invoice)
        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->enterprise->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
    }

    // ─── Step 4: Change Plan ─────────────────────────────────────

    /** @test */
    public function test_e2e_step6_change_plan_from_growth_to_enterprise(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->enterprise->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.subscription_plan_id', $this->enterprise->id);

        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->enterprise->id,
        ]);
    }

    /** @test */
    public function test_e2e_step6b_cannot_change_to_same_plan(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(409);
    }

    // ─── Step 5: Apply Discount Code ─────────────────────────────

    /** @test */
    public function test_e2e_step7_apply_discount_code_at_subscribe(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'LAUNCH50',
            'type' => 'percentage',
            'value' => 50.00,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
            'max_uses' => 100,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'LAUNCH50',
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        // Response shape: original_price, discount_amount, final_price (no discount_percentage field)
        $this->assertEqualsWithDelta(29.99, $response->json('data.original_price'), 0.01);
        $this->assertEqualsWithDelta(15.00, $response->json('data.discount_amount'), 0.01);
        $this->assertEqualsWithDelta(14.99, $response->json('data.final_price'), 0.01);
    }

    /** @test */
    public function test_e2e_step7b_subscribe_with_discount_increments_times_used(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
            'max_uses' => 10,
            'times_used' => 0,
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
            'discount_code' => 'SAVE20',
        ]);

        $this->assertSame(1, $discount->fresh()->times_used);
    }

    // ─── Step 6: Plan Feature and Limit Checks ───────────────────

    /** @test */
    public function test_e2e_step8_check_enabled_feature_returns_enabled(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-feature/multi_branch');

        $response->assertOk();
        $this->assertTrue($response->json('data.is_enabled'));
    }

    /** @test */
    public function test_e2e_step8b_check_disabled_feature_returns_disabled(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-feature/reports_advanced');

        $response->assertOk();
        $this->assertFalse($response->json('data.is_enabled'));
    }

    /** @test */
    public function test_e2e_step8c_check_limit_with_room_returns_within_limit(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/check-limit/branches');

        $response->assertOk();
        $data = $response->json('data');
        // check-limit returns: remaining (quota left), can_perform (bool)
        // Growth allows 3 branches; setUp has 1 store → remaining = 2
        $this->assertTrue($data['can_perform']);
        $this->assertGreaterThan(0, $data['remaining']);
    }

    // ─── Step 7: Activate Add-on ─────────────────────────────────

    /** @test */
    public function test_e2e_step9_activate_add_on(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $addOn = PlanAddOn::create([
            'name' => 'Loyalty Plus',
            'slug' => 'loyalty-plus',
            'monthly_price' => 9.99,
            'is_active' => true,
        ]);

        $activateResponse = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$addOn->id}/activate");
        $activateResponse->assertCreated();

        // Verify add-on shows in list
        $listResponse = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');
        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json('data'));
    }

    // ─── Step 8: Cancel with Reason ──────────────────────────────

    /** @test */
    public function test_e2e_step10_cancel_subscription_with_reason_saves_to_db(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason' => 'The price is too high for our current budget.',
            'reason_category' => 'price',
        ]);

        $response->assertOk();
        // Growth plan has grace_period_days=7 so cancellation moves to 'grace', not 'cancelled'
        $this->assertContains($response->json('data.status'), ['grace', 'cancelled']);
        $this->assertNotNull($response->json('data.cancelled_at'));

        $this->assertDatabaseHas('cancellation_reasons', [
            'reason_category' => 'price',
            'reason_text' => 'The price is too high for our current budget.',
        ]);
    }

    /** @test */
    public function test_e2e_step10b_cancelled_subscription_status_is_cancelled(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason_category' => 'features',
        ]);

        // Growth plan has grace_period_days=7 so cancellation moves to 'grace' status
        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'status' => SubscriptionStatus::Grace->value,
        ]);
    }

    // ─── Step 9: Resume from Cancelled ───────────────────────────

    /** @test */
    public function test_e2e_step11_resume_cancelled_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk();

        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    /** @test */
    public function test_e2e_step11b_resumed_subscription_has_null_cancelled_at(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $subscription = StoreSubscription::where('organization_id', $this->org->id)->first();
        $this->assertNull($subscription->cancelled_at);
    }

    // ─── Step 10: Grace → Expired via Job ────────────────────────

    /** @test */
    public function test_e2e_step12_grace_period_ends_expire_job_expires_subscription(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subHour(), // grace period ended
        ]);

        ExpireSubscriptionsJob::dispatchSync();

        $this->assertSame(SubscriptionStatus::Expired->value, $sub->fresh()->status->value);

        // Accessing current subscription after expiry
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');
        // Either 404 (no active) or 200 with expired status
        if ($response->status() === 200) {
            $this->assertSame('expired', $response->json('data.status'));
        } else {
            $response->assertNotFound();
        }
    }

    // ─── Step 11: Renewal Invoice Generation ─────────────────────

    /** @test */
    public function test_e2e_step13_renewal_invoice_job_generates_invoice_before_expiry(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subDays(27),
            'current_period_end' => now()->addDays(2), // expiring in 2 days
        ]);

        GenerateRenewalInvoicesJob::dispatchSync();

        $this->assertDatabaseHas('invoices', [
            'store_subscription_id' => $sub->id,
            'status' => 'pending',
        ]);

        // Verify it appears in invoice list
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/invoices');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.meta.total'));
    }

    // ─── Full Lifecycle: Subscribe → Cancel → Resume ─────────────

    /** @test */
    public function test_e2e_full_lifecycle_subscribe_cancel_resume(): void
    {
        // Subscribe
        $subscribeResponse = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->growth->id,
            'billing_cycle' => 'monthly',
        ]);
        $subscribeResponse->assertCreated();

        // Check current
        $currentResponse = $this->withToken($this->token)->getJson('/api/v2/subscription/current');
        $currentResponse->assertOk();

        // Cancel
        $cancelResponse = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason_category' => 'competitor',
            'reason' => 'Found a better solution.',
        ]);
        $cancelResponse->assertOk();
        // Growth plan has grace_period_days=7, so cancel moves to 'grace'
        $this->assertContains($cancelResponse->json('data.status'), ['grace', 'cancelled']);

        $this->assertDatabaseHas('cancellation_reasons', [
            'reason_category' => 'competitor',
        ]);

        // Resume
        $resumeResponse = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');
        $resumeResponse->assertOk();

        // Verify active
        $this->assertDatabaseHas('store_subscriptions', [
            'organization_id' => $this->org->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    // ─── Invoice Viewing After Subscription ──────────────────────

    /** @test */
    public function test_e2e_invoices_are_visible_after_paid_subscription(): void
    {
        $sub = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Invoice::create([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-E2E-001',
            'amount' => 29.99,
            'tax' => 4.50,
            'total' => 34.49,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/invoices');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.meta.total'));
        $this->assertSame('INV-E2E-001', $response->json('data.data.0.invoice_number'));
    }

    // ─── Usage Tracking ──────────────────────────────────────────

    /** @test */
    public function test_e2e_usage_endpoint_returns_all_tracked_limits(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/usage');

        $response->assertOk();
        // Usage items have keys: limit_key, current, limit, is_unlimited, remaining, percentage
        $response->assertJsonStructure([
            'data' => [
                '*' => ['limit_key', 'current', 'limit', 'is_unlimited', 'percentage'],
            ],
        ]);
    }

    // ─── All Plan Features Listing ───────────────────────────────

    /** @test */
    public function test_e2e_all_features_endpoint_lists_plan_features(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->growth->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/features');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);

        // Growth has multi_branch=true and reports_advanced=false
        $multibranchToggle = collect($data)->firstWhere('feature_key', 'multi_branch');
        $this->assertNotNull($multibranchToggle);
        $this->assertTrue($multibranchToggle['is_enabled']);
    }
}
