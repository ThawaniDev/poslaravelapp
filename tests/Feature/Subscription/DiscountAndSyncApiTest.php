<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for:
 *  - POST /api/v2/subscription/validate-discount
 *  - GET  /api/v2/subscription/sync
 *  - GET  /api/v2/subscription/features
 *  - GET  /api/v2/subscription/feature-route-mapping
 */
class DiscountAndSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Discount Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Discount Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@discount.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 100.00,
            'annual_price' => 1000.00,
            'trial_days' => 0,
            'grace_period_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->subscription = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Add some features for sync test
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'pos',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'inventory',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'analytics',
            'is_enabled' => false,
        ]);
    }

    // ─── Validate Discount — Percentage ──────────────────────────

    public function test_validate_percentage_discount_returns_discounted_price(): void
    {
        SubscriptionDiscount::create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.00,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'SAVE20',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'SAVE20')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.billing_cycle', 'monthly');

        $this->assertEquals(20.0, (float) $response->json('data.value'));
        $this->assertEquals(100.0, (float) $response->json('data.original_price'));
        $this->assertEquals(20.0, (float) $response->json('data.discount_amount'));
        $this->assertEquals(80.0, (float) $response->json('data.final_price'));
    }

    public function test_validate_percentage_discount_yearly_billing(): void
    {
        SubscriptionDiscount::create([
            'code' => 'YEAR15',
            'type' => 'percentage',
            'value' => 15.00,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'YEAR15',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'yearly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.billing_cycle', 'yearly');

        $this->assertEquals(1000.0, (float) $response->json('data.original_price'));
        $this->assertEquals(150.0, (float) $response->json('data.discount_amount'));
        $this->assertEquals(850.0, (float) $response->json('data.final_price'));
    }

    // ─── Validate Discount — Fixed Amount ────────────────────────

    public function test_validate_fixed_discount_deducts_fixed_amount(): void
    {
        SubscriptionDiscount::create([
            'code' => 'FLAT30',
            'type' => 'fixed',
            'value' => 30.00,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'FLAT30',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'fixed');

        $this->assertEquals(30.0, (float) $response->json('data.discount_amount'));
        $this->assertEquals(70.0, (float) $response->json('data.final_price'));
    }

    public function test_validate_fixed_discount_capped_at_plan_price(): void
    {
        SubscriptionDiscount::create([
            'code' => 'HUGE500',
            'type' => 'fixed',
            'value' => 500.00,  // more than plan price of 100
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'HUGE500',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $this->assertEquals(0.0, (float) $response->json('data.final_price'));
    }

    // ─── Validate Discount — Invalid / Expired / Not Applicable ──

    public function test_validate_discount_with_invalid_code_returns_422(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'NOTEXIST',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_validate_discount_expired_by_max_uses_returns_422(): void
    {
        SubscriptionDiscount::create([
            'code' => 'USED100',
            'type' => 'percentage',
            'value' => 10.00,
            'max_uses' => 10,
            'times_used' => 10, // fully used
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'USED100',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_validate_discount_not_yet_valid_returns_422(): void
    {
        SubscriptionDiscount::create([
            'code' => 'FUTURE',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->addDays(7), // not valid yet
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'FUTURE',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_validate_discount_past_valid_to_returns_422(): void
    {
        SubscriptionDiscount::create([
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 20.00,
            'valid_from' => now()->subMonths(2),
            'valid_to' => now()->subDay(), // expired
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'EXPIRED',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_validate_discount_not_applicable_to_plan_returns_422(): void
    {
        $otherPlan = SubscriptionPlan::create([
            'name' => 'Other Plan',
            'slug' => 'other',
            'monthly_price' => 50.00,
            'annual_price' => 500.00,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        SubscriptionDiscount::create([
            'code' => 'ONLYGROWTH',
            'type' => 'percentage',
            'value' => 20.00,
            'times_used' => 0,
            'applicable_plan_ids' => [$otherPlan->id], // only for the other plan
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'ONLYGROWTH',
            'plan_id' => $this->plan->id, // current plan, not in applicable_plan_ids
        ]);

        $response->assertUnprocessable();
    }

    public function test_validate_discount_applicable_to_any_plan_when_ids_empty(): void
    {
        SubscriptionDiscount::create([
            'code' => 'ANYPLAN',
            'type' => 'percentage',
            'value' => 5.00,
            'times_used' => 0,
            'applicable_plan_ids' => [], // empty = applies to all
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'ANYPLAN',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk();
    }

    public function test_validate_discount_code_is_case_insensitive(): void
    {
        SubscriptionDiscount::create([
            'code' => 'UPPER10',
            'type' => 'percentage',
            'value' => 10.00,
            'times_used' => 0,
        ]);

        // Submit lowercase — should still match
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'upper10',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'UPPER10');
    }

    public function test_validate_discount_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'plan_id']);
    }

    public function test_validate_discount_with_invalid_plan_id_returns_404(): void
    {
        SubscriptionDiscount::create([
            'code' => 'VALID10',
            'type' => 'percentage',
            'value' => 10.00,
            'times_used' => 0,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'VALID10',
            'plan_id' => '00000000-0000-0000-0000-000000000000', // invalid
        ]);

        $response->assertStatus(404);
    }

    public function test_validate_discount_requires_auth(): void
    {
        $response = $this->postJson('/api/v2/subscription/validate-discount', [
            'code' => 'TEST',
            'plan_id' => $this->plan->id,
        ]);

        $response->assertUnauthorized();
    }

    // ─── Sync Entitlements ────────────────────────────────────────

    public function test_sync_entitlements_returns_all_required_keys(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_subscription',
                    'status',
                    'plan_code',
                    'plan_name',
                    'plan_id',
                    'billing_cycle',
                    'expires_at',
                    'features',
                    'limits',
                    'softpos',
                    'is_softpos_free',
                    'subscription',
                    'plan',
                    'synced_at',
                    'feature_route_mapping',
                ],
            ]);
    }

    public function test_sync_entitlements_features_are_booleans(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk();
        $features = $response->json('data.features');

        $this->assertIsArray($features);
        foreach ($features as $key => $value) {
            $this->assertIsBool($value, "Feature '{$key}' should be a boolean");
        }
    }

    public function test_sync_entitlements_feature_route_mapping_is_non_empty(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk();
        $mapping = $response->json('data.feature_route_mapping');

        $this->assertIsArray($mapping);
        $this->assertNotEmpty($mapping);
    }

    public function test_sync_entitlements_has_subscription_true_when_active(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk()
            ->assertJsonPath('data.has_subscription', true)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_sync_entitlements_has_subscription_false_when_none(): void
    {
        $this->subscription->delete();

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk()
            ->assertJsonPath('data.has_subscription', false);
    }

    public function test_sync_entitlements_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/sync/entitlements');
        $response->assertUnauthorized();
    }

    public function test_sync_entitlements_has_synced_at_timestamp(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/sync/entitlements');

        $response->assertOk();
        $this->assertNotNull($response->json('data.synced_at'));
    }

    // ─── GET /features ────────────────────────────────────────────

    public function test_all_features_returns_array(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/features');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_all_features_includes_plan_features(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/features');

        $response->assertOk();
        $features = $response->json('data');

        // Response is an array of objects: [{feature_key, name, name_ar, is_enabled}]
        $featureKeys = array_column($features, 'feature_key');
        $this->assertContains('pos', $featureKeys);
        $this->assertContains('inventory', $featureKeys);
        $this->assertContains('analytics', $featureKeys);
    }

    public function test_all_features_objects_have_required_keys(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/features');

        $response->assertOk();
        $features = $response->json('data');
        $this->assertNotEmpty($features);

        foreach ($features as $feature) {
            $this->assertArrayHasKey('feature_key', $feature);
            $this->assertArrayHasKey('is_enabled', $feature);
            $this->assertIsBool($feature['is_enabled'], "is_enabled should be a boolean");
        }
    }

    public function test_all_features_requires_organization(): void
    {
        $userNoOrg = User::create([
            'name' => 'No Org User',
            'email' => 'noorg@features.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->withToken($userNoOrg->createToken('test', ['*'])->plainTextToken)
            ->getJson('/api/v2/subscription/features');

        $response->assertNotFound();
    }

    public function test_all_features_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/features');
        $response->assertUnauthorized();
    }

    // ─── GET /feature-route-mapping ───────────────────────────────

    public function test_feature_route_mapping_returns_non_empty_map(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/feature-route-mapping');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_feature_route_mapping_does_not_require_subscription(): void
    {
        $this->subscription->delete();

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/feature-route-mapping');

        // Route mapping is static, doesn't depend on subscription
        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_feature_route_mapping_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/subscription/feature-route-mapping');
        $response->assertUnauthorized();
    }

    // ─── Resume from Grace Period (supplement) ────────────────────

    public function test_resume_from_grace_period_returns_active(): void
    {
        $this->subscription->update([
            'status' => SubscriptionStatus::Grace->value,
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNull($response->json('data.cancelled_at'));
    }

    public function test_resume_from_cancelled_returns_active(): void
    {
        $this->subscription->update([
            'status' => SubscriptionStatus::Cancelled->value,
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/resume');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }
}
