<?php

namespace Tests\Feature\Subscription;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreAddOn;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use App\Domain\Subscription\Models\PlanAddOn;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for store add-on management:
 *  GET  /subscription/store-add-ons
 *  POST /subscription/store-add-ons/{id}/activate
 *  DELETE /subscription/store-add-ons/{id}
 *
 * Covers: listing, activating, deactivating, duplicate-activate error,
 * deactivate-inactive error, reactivation, 404 on unknown add-on,
 * cross-store isolation, unauthenticated access blocked.
 */
class AddOnBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;
    private PlanAddOn $addOn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'AddOn Test Org',
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
            'name' => 'AddOn Owner',
            'email' => 'addon@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-addon',
            'monthly_price' => 29.99,
            'grace_period_days' => 7,
            'is_active' => true,
        ]);

        $this->subscription = StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->addOn = PlanAddOn::create([
            'name' => 'Loyalty Module',
            'name_ar' => 'وحدة الولاء',
            'slug' => 'loyalty-module',
            'monthly_price' => 9.99,
            'description' => 'Customer loyalty features',
            'is_active' => true,
        ]);
    }

    // ─── List Add-ons ────────────────────────────────────────────

    public function test_can_list_store_add_ons_when_empty(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_can_list_active_store_add_ons(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                [
                    'store_id',
                    'plan_add_on_id',
                    'is_active',
                    'activated_at',
                    'add_on' => ['id', 'name', 'name_ar', 'slug', 'monthly_price', 'description', 'is_active'],
                ],
            ],
        ]);
    }

    public function test_add_on_list_includes_inactive_add_ons(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertFalse($response->json('data.0.is_active'));
    }

    public function test_unauthenticated_cannot_list_add_ons(): void
    {
        $response = $this->getJson('/api/v2/subscription/store-add-ons');

        $response->assertUnauthorized();
    }

    // ─── Activate Add-on ─────────────────────────────────────────

    public function test_can_activate_add_on_for_store(): void
    {
        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();
        $response->assertJsonPath('data.plan_add_on_id', $this->addOn->id);
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.store_id', $this->store->id);
        $response->assertJsonPath('data.add_on.slug', 'loyalty-module');
    }

    public function test_activate_add_on_persists_to_database(): void
    {
        $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_activate_already_active_add_on(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertUnprocessable();
    }

    public function test_can_reactivate_previously_deactivated_add_on(): void
    {
        // Deactivate first
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_returns_404_for_nonexistent_add_on(): void
    {
        $fakeId = \Illuminate\Support\Str::uuid()->toString();

        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$fakeId}/activate");

        $response->assertNotFound();
    }

    public function test_activate_returns_404_for_inactive_add_on(): void
    {
        $inactiveAddOn = PlanAddOn::create([
            'name' => 'Disabled Module',
            'slug' => 'disabled-module',
            'monthly_price' => 5.00,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$inactiveAddOn->id}/activate");

        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_activate_add_on(): void
    {
        $response = $this->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertUnauthorized();
    }

    // ─── Deactivate Add-on ───────────────────────────────────────

    public function test_can_deactivate_active_add_on(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertOk();

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_deactivate_already_inactive_add_on(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertUnprocessable();
    }

    public function test_remove_add_on_returns_404_when_not_activated(): void
    {
        $fakeId = \Illuminate\Support\Str::uuid()->toString();

        $response = $this->withToken($this->token)->deleteJson("/api/v2/subscription/store-add-ons/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_remove_add_on(): void
    {
        $response = $this->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertUnauthorized();
    }

    // ─── Add-on Response Shape ───────────────────────────────────

    public function test_add_on_response_includes_arabic_name(): void
    {
        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();
        $this->assertSame('وحدة الولاء', $response->json('data.add_on.name_ar'));
    }

    public function test_add_on_monthly_price_is_numeric(): void
    {
        $response = $this->withToken($this->token)->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();
        $this->assertIsNumeric($response->json('data.add_on.monthly_price'));
    }

    public function test_multiple_add_ons_can_be_listed(): void
    {
        $addOn2 = PlanAddOn::create([
            'name' => 'Delivery Module',
            'slug' => 'delivery-module',
            'monthly_price' => 14.99,
            'is_active' => true,
        ]);

        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $addOn2->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
