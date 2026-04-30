<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\PlanLimit;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
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
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
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

        // Create test plans
        $this->starterPlan = SubscriptionPlan::create([
            'name' => 'Starter',
            'name_ar' => 'المبتدئ',
            'slug' => 'starter',
            'monthly_price' => 0,
            'annual_price' => 0,
            'trial_days' => 14,
            'grace_period_days' => 3,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);

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

        $this->growthPlan = SubscriptionPlan::create([
            'name' => 'Growth',
            'name_ar' => 'النمو',
            'slug' => 'growth',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => true,
            'sort_order' => 2,
        ]);

        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->growthPlan->id,
            'feature_key' => 'pos',
            'is_enabled' => true,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->growthPlan->id,
            'feature_key' => 'multi_branch',
            'is_enabled' => true,
        ]);
        PlanLimit::create([
            'subscription_plan_id' => $this->growthPlan->id,
            'limit_key' => 'products',
            'limit_value' => 1000,
            'price_per_extra_unit' => 0.05,
        ]);
    }

    // ─── List Plans ──────────────────────────────────────────────

    public function test_can_list_active_plans(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'name_ar', 'slug', 'monthly_price', 'annual_price', 'features', 'limits'],
                ],
            ]);

        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_list_plans_hides_inactive(): void
    {
        SubscriptionPlan::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'monthly_price' => 10,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/v2/subscription/plans?active_only=1');
        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_list_plans_includes_inactive_when_requested(): void
    {
        SubscriptionPlan::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'monthly_price' => 10,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/v2/subscription/plans?active_only=0');
        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
    }

    // ─── Get Single Plan ─────────────────────────────────────────

    public function test_can_get_plan_by_id(): void
    {
        $response = $this->getJson("/api/v2/subscription/plans/{$this->starterPlan->id}");

        $response->assertOk()
            ->assertJsonPath('data.slug', 'starter')
            ->assertJsonPath('data.monthly_price', 0);

        // Verify features and limits are included
        $this->assertNotEmpty($response->json('data.features'));
        $this->assertNotEmpty($response->json('data.limits'));
    }

    public function test_get_plan_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ─── Get Plan by Slug ────────────────────────────────────────

    public function test_can_get_plan_by_slug(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans/slug/growth');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Growth')
            ->assertJsonPath('data.monthly_price', 29.99);
    }

    public function test_get_plan_by_slug_returns_404_for_unknown(): void
    {
        $response = $this->getJson('/api/v2/subscription/plans/slug/00000000-0000-0000-0000-000000000099');

        $response->assertNotFound();
    }

    // ─── Compare Plans ───────────────────────────────────────────

    public function test_can_compare_plans(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans/compare', [
            'plan_ids' => [$this->starterPlan->id, $this->growthPlan->id],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['plans', 'features', 'limits'],
            ]);

        $this->assertEquals(2, count($response->json('data.plans')));
    }

    public function test_compare_plans_requires_at_least_two(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans/compare', [
            'plan_ids' => [$this->starterPlan->id],
        ]);

        $response->assertUnprocessable();
    }

    // ─── Create Plan (Admin) ─────────────────────────────────────

    public function test_can_create_plan(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans', [
            'name' => 'Enterprise',
            'name_ar' => 'المؤسسات',
            'slug' => 'enterprise',
            'monthly_price' => 99.99,
            'annual_price' => 999.99,
            'trial_days' => 30,
            'is_active' => true,
            'features' => [
                ['feature_key' => 'pos', 'is_enabled' => true],
                ['feature_key' => 'api_access', 'is_enabled' => true],
            ],
            'limits' => [
                ['limit_key' => 'products', 'limit_value' => 100000, 'price_per_extra_unit' => 0.01],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Enterprise')
            ->assertJsonPath('data.slug', 'enterprise');

        $this->assertDatabaseHas('subscription_plans', ['slug' => 'enterprise']);
        $this->assertDatabaseHas('plan_feature_toggles', [
            'feature_key' => 'api_access',
            'is_enabled' => true,
        ]);
    }

    public function test_create_plan_fails_with_duplicate_slug(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans', [
            'name' => 'Starter Dupe',
            'slug' => 'starter', // Already exists
            'monthly_price' => 5.00,
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_plan_validation_requires_name(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans', [
            'slug' => 'new-plan',
            'monthly_price' => 10,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_plan_validation_rejects_negative_price(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/subscription/plans', [
            'name' => 'Bad Plan',
            'slug' => 'bad-plan',
            'monthly_price' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['monthly_price']);
    }

    // ─── Update Plan ─────────────────────────────────────────────

    public function test_can_update_plan(): void
    {
        $response = $this->withToken($this->token)->putJson(
            "/api/v2/subscription/plans/{$this->growthPlan->id}",
            [
                'name' => 'Growth Pro',
                'monthly_price' => 39.99,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Growth Pro')
            ->assertJsonPath('data.monthly_price', 39.99);
    }

    public function test_update_nonexistent_plan_returns_404(): void
    {
        $response = $this->withToken($this->token)->putJson(
            '/api/v2/subscription/plans/00000000-0000-0000-0000-000000000000',
            ['name' => 'Ghost']
        );

        $response->assertNotFound();
    }

    // ─── Toggle Plan ─────────────────────────────────────────────

    public function test_can_toggle_plan_active_status(): void
    {
        $this->assertTrue($this->growthPlan->is_active);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v2/subscription/plans/{$this->growthPlan->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Toggle back
        $response2 = $this->withToken($this->token)
            ->patchJson("/api/v2/subscription/plans/{$this->growthPlan->id}/toggle");

        $response2->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    // ─── Delete Plan ─────────────────────────────────────────────

    public function test_can_delete_plan_without_subscribers(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/plans/{$this->starterPlan->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('subscription_plans', ['id' => $this->starterPlan->id]);
    }

    public function test_delete_plan_with_subscribers_returns_conflict(): void
    {
        // Create a subscription
        \App\Domain\ProviderSubscription\Models\StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/plans/{$this->starterPlan->id}");

        $response->assertStatus(409);
    }

    // ─── Add-Ons ─────────────────────────────────────────────────

    public function test_can_list_add_ons(): void
    {
        PlanAddOn::create([
            'name' => 'Extra Storage',
            'slug' => 'extra-storage',
            'monthly_price' => 4.99,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/subscription/add-ons');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ─── Response Structure ──────────────────────────────────────

    public function test_plan_response_has_correct_structure(): void
    {
        $response = $this->getJson("/api/v2/subscription/plans/{$this->growthPlan->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'name_ar',
                    'slug',
                    'monthly_price',
                    'annual_price',
                    'trial_days',
                    'grace_period_days',
                    'is_active',
                    'is_highlighted',
                    'sort_order',
                    'features' => [
                        '*' => ['feature_key', 'is_enabled'],
                    ],
                    'limits' => [
                        '*' => ['limit_key', 'limit_value'],
                    ],
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
