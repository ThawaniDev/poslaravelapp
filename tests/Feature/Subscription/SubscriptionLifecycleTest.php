<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Models\CancellationReason;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the complete subscription lifecycle:
 *  Trial → Active → Grace → Expired → Cancelled → Resume
 *
 * Also covers cancellation with reason_category and the CancellationReason
 * table persistence introduced in this session.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private string $token;
    private SubscriptionPlan $freePlan;
    private SubscriptionPlan $paidPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Lifecycle Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Lifecycle Owner',
            'email' => 'lifecycle@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => Store::where('organization_id', $this->org->id)->first()->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->freePlan = SubscriptionPlan::create([
            'name' => 'Free',
            'slug' => 'free-lifecycle',
            'monthly_price' => 0,
            'annual_price' => 0,
            'trial_days' => 14,
            'grace_period_days' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->paidPlan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-lifecycle',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    // ─── Trial Lifecycle ─────────────────────────────────────────

    public function test_subscribe_to_trial_plan_starts_in_trial_status(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->freePlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'trial');
        $response->assertJsonPath('data.trial_ends_at', fn ($v) => $v !== null);
    }

    public function test_trial_subscription_has_correct_trial_period(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->freePlan->id,
        ]);

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();

        $this->assertNotNull($sub->trial_ends_at);
        // Trial should end approximately 14 days from now
        $this->assertTrue($sub->trial_ends_at->greaterThan(now()->addDays(13)));
        $this->assertTrue($sub->trial_ends_at->lessThan(now()->addDays(15)));
    }

    public function test_trial_subscription_does_not_generate_invoice(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->freePlan->id,
        ]);

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();

        $this->assertSame(0, $sub->invoices()->count());
    }

    // ─── Active Lifecycle ────────────────────────────────────────

    public function test_subscribe_to_paid_plan_starts_in_active_status(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        $this->assertNull($response->json('data.trial_ends_at'));
    }

    public function test_active_subscription_has_period_end_date(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();

        $this->assertNotNull($sub->current_period_end);
        // Monthly: ~30 days ahead
        $this->assertTrue($sub->current_period_end->greaterThan(now()->addDays(28)));
    }

    public function test_yearly_subscription_period_end_is_one_year_ahead(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'yearly',
        ]);

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();

        $this->assertTrue($sub->current_period_end->greaterThan(now()->addDays(364)));
        $this->assertTrue($sub->current_period_end->lessThan(now()->addDays(366)));
    }

    // ─── Grace Period Transition ─────────────────────────────────

    public function test_cancel_active_subscription_enters_grace_period(): void
    {
        // Create active subscription
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason' => 'Switching to competitor',
            'reason_category' => 'competitor',
        ]);

        $response->assertOk();
        // Plan has grace_period_days = 7
        $response->assertJsonPath('data.status', 'grace');
        $response->assertJsonPath('data.cancelled_at', fn ($v) => $v !== null);
    }

    public function test_cancel_plan_without_grace_transitions_directly_to_cancelled(): void
    {
        $noGracePlan = SubscriptionPlan::create([
            'name' => 'No Grace',
            'slug' => 'no-grace-lifecycle',
            'monthly_price' => 9.99,
            'grace_period_days' => 0,
            'is_active' => true,
        ]);

        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $noGracePlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_with_reason_text_creates_cancellation_reason_record(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason' => 'Too expensive for our budget',
            'reason_category' => 'price',
        ]);

        $this->assertDatabaseHas('cancellation_reasons', [
            'reason_category' => 'price',
            'reason_text' => 'Too expensive for our budget',
        ]);
    }

    public function test_cancel_without_reason_does_not_create_cancellation_record(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/cancel');

        $this->assertDatabaseCount('cancellation_reasons', 0);
    }

    public function test_cancel_with_invalid_reason_category_returns_validation_error(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
            'reason_category' => 'invalid_category',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason_category']);
    }

    public function test_all_valid_reason_categories_are_accepted(): void
    {
        $categories = CancellationReason::CATEGORIES;

        foreach ($categories as $category) {
            // Create new subscription for each iteration
            $sub = StoreSubscription::create([
                'organization_id' => $this->org->id,
                'subscription_plan_id' => $this->paidPlan->id,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $response = $this->withToken($this->token)->postJson('/api/v2/subscription/cancel', [
                'reason_category' => $category,
                'reason' => "Cancelled due to: {$category}",
            ]);

            $response->assertOk();

            // Clean up for next iteration - delete the cancellation and reactivate
            $sub->delete();
            CancellationReason::truncate();
        }
    }

    // ─── Resume Lifecycle ────────────────────────────────────────

    public function test_resume_grace_subscription_becomes_active(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(5),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $this->assertNull($response->json('data.cancelled_at'));
    }

    public function test_resume_cancelled_subscription_becomes_active(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_resume_generates_new_billing_period(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Cancelled,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();

        $this->assertTrue($sub->current_period_end->greaterThan(now()));
        $this->assertNull($sub->cancelled_at);
    }

    public function test_cannot_resume_active_subscription(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertNotFound();
    }

    // ─── Plan Change ─────────────────────────────────────────────

    public function test_change_plan_updates_subscription_plan(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->freePlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();
        $this->assertSame($this->paidPlan->id, $sub->subscription_plan_id);
        $this->assertSame('active', $sub->status->value);
    }

    public function test_change_plan_resets_trial_status_to_active(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->freePlan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(10),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(10),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->paidPlan->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_change_plan_to_same_plan_returns_conflict(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->putJson('/api/v2/subscription/change-plan', [
            'plan_id' => $this->paidPlan->id,
        ]);

        $response->assertStatus(409);
    }

    // ─── Get Current Subscription ────────────────────────────────

    public function test_get_current_subscription_returns_correct_shape(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'organization_id',
                'subscription_plan_id',
                'status',
                'billing_cycle',
                'current_period_start',
                'current_period_end',
                'is_softpos_free',
                'softpos_transaction_count',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_get_current_returns_null_when_no_subscription(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $this->assertNull($response->json('data'));
    }

    public function test_get_current_includes_plan_data(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $response->assertJsonPath('data.plan.name', 'Growth');
        $response->assertJsonPath('data.plan.monthly_price', 29.99);
    }

    // ─── Cannot Subscribe Twice ──────────────────────────────────

    public function test_cannot_subscribe_when_already_active(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->freePlan->id,
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_subscribe_when_in_trial(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->freePlan->id,
            'status' => SubscriptionStatus::Trial,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(10),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(10),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->paidPlan->id,
        ]);

        $response->assertStatus(409);
    }

    // ─── Grace Period Info in Response ──────────────────────────

    public function test_grace_subscription_response_includes_grace_period_ends_at(): void
    {
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->paidPlan->id,
            'status' => SubscriptionStatus::Grace,
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(7),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/current');

        $response->assertOk();
        $response->assertJsonPath('data.grace_period_ends_at', fn ($v) => $v !== null);
    }
}
