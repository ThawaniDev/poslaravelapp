<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the discount code validation and subscription discount flow.
 *
 * Covers: validate-discount endpoint, subscribe with discount,
 * expired codes, max_uses exhausted, plan-specific discounts,
 * code normalization, discount increment, percentage and fixed types.
 */
class DiscountCodeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private string $token;
    private SubscriptionPlan $plan;
    private SubscriptionPlan $otherPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Discount Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Discount Owner',
            'email' => 'discount@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-discount',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
        ]);

        $this->otherPlan = SubscriptionPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise-discount',
            'monthly_price' => 99.99,
            'is_active' => true,
        ]);
    }

    // ─── validate-discount Endpoint ──────────────────────────────

    public function test_valid_percentage_discount_code_is_accepted(): void
    {
        SubscriptionDiscount::create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.00,
            'max_uses' => 100,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'SAVE20',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.type', 'percentage');
        $this->assertEqualsWithDelta(20.0, $response->json('data.value'), 0.001);
    }

    public function test_valid_fixed_discount_code_is_accepted(): void
    {
        SubscriptionDiscount::create([
            'code' => 'FLAT10',
            'type' => 'fixed',
            'value' => 10.00,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'FLAT10',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.type', 'fixed');
    }

    public function test_invalid_discount_code_returns_invalid(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'DOESNOTEXIST',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_expired_discount_code_returns_invalid(): void
    {
        SubscriptionDiscount::create([
            'code' => 'EXPIRED20',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'EXPIRED20',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_future_discount_code_returns_invalid(): void
    {
        SubscriptionDiscount::create([
            'code' => 'FUTURE20',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->addWeek(),
            'valid_to' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'FUTURE20',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_max_uses_exhausted_discount_returns_invalid(): void
    {
        SubscriptionDiscount::create([
            'code' => 'MAXOUT',
            'type' => 'percentage',
            'value' => 15.00,
            'max_uses' => 10,
            'times_used' => 10, // fully exhausted
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'MAXOUT',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_plan_specific_discount_valid_for_correct_plan(): void
    {
        SubscriptionDiscount::create([
            'code' => 'GROWTHONLY',
            'type' => 'percentage',
            'value' => 25.00,
            'applicable_plan_ids' => [$this->plan->id],
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'GROWTHONLY',
            'plan_id' => $this->plan->id, // correct plan
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'GROWTHONLY');
    }

    public function test_plan_specific_discount_invalid_for_wrong_plan(): void
    {
        SubscriptionDiscount::create([
            'code' => 'GROWTHONLY2',
            'type' => 'percentage',
            'value' => 25.00,
            'applicable_plan_ids' => [$this->plan->id],
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'GROWTHONLY2',
            'plan_id' => $this->otherPlan->id, // wrong plan
        ]);

        $response->assertUnprocessable();
    }

    public function test_discount_code_is_case_insensitive(): void
    {
        SubscriptionDiscount::create([
            'code' => 'UPPER20',
            'type' => 'percentage',
            'value' => 20.00,
        ]);

        // Send lowercase
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'upper20',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'UPPER20');
    }

    // ─── Subscribe with Discount ─────────────────────────────────

    public function test_subscribe_with_valid_discount_applies_it(): void
    {
        SubscriptionDiscount::create([
            'code' => 'SUBSAVE10',
            'type' => 'percentage',
            'value' => 10.00,
            'max_uses' => 50,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'discount_code' => 'SUBSAVE10',
        ]);

        $response->assertCreated();

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();
        $invoice = $sub->invoices()->first();

        $this->assertNotNull($invoice);
        $this->assertLessThan(29.99 * 1.15, $invoice->total); // total should be discounted
    }

    public function test_subscribe_with_discount_increments_times_used(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'COUNTME',
            'type' => 'percentage',
            'value' => 5.00,
            'max_uses' => 100,
            'times_used' => 5,
        ]);

        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'discount_code' => 'COUNTME',
        ]);

        $discount->refresh();
        $this->assertSame(6, (int) $discount->times_used);
    }

    public function test_subscribe_with_invalid_discount_code_returns_error(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'discount_code' => 'BADINVALIDCODE',
        ]);

        $response->assertStatus(409);
    }

    public function test_subscribe_with_expired_discount_code_returns_error(): void
    {
        SubscriptionDiscount::create([
            'code' => 'SUBEXPIRED',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_to' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'discount_code' => 'SUBEXPIRED',
        ]);

        $response->assertStatus(409);
    }

    public function test_subscribe_without_discount_generates_full_price_invoice(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/subscription/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $sub = StoreSubscription::where('organization_id', $this->org->id)->first();
        $invoice = $sub->invoices()->first();

        $this->assertNotNull($invoice);
        // 29.99 * 1.15 VAT = 34.49 approx
        $this->assertEqualsWithDelta(34.49, $invoice->total, 0.5);
    }

    // ─── validate-discount Response Shape ───────────────────────

    public function test_validate_discount_returns_calculated_discount_amount(): void
    {
        SubscriptionDiscount::create([
            'code' => 'CALCTEST',
            'type' => 'percentage',
            'value' => 20.00,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'CALCTEST',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'code',
                'type',
                'value',
                'original_price',
                'discount_amount',
                'final_price',
                'currency',
                'billing_cycle',
            ],
        ]);
    }

    public function test_validate_discount_requires_authentication(): void
    {
        $response = $this->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'TEST',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnauthorized();
    }
}
