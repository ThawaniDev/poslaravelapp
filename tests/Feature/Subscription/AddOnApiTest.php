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
 * Feature tests for the Add-On API endpoints.
 *
 * Covers:
 *  - GET  /api/v2/subscription/store-add-ons          (list)
 *  - POST /api/v2/subscription/store-add-ons/{id}/activate (activate / reactivate)
 *  - DELETE /api/v2/subscription/store-add-ons/{id}   (remove / deactivate)
 */
class AddOnApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;
    private PlanAddOn $addOn;
    private PlanAddOn $inactiveAddOn;
    private PlanAddOn $freeAddOn;

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
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@addon.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        // Active paid add-on
        $this->addOn = PlanAddOn::create([
            'name' => 'Loyalty Module',
            'name_ar' => 'وحدة الولاء',
            'slug' => 'loyalty',
            'monthly_price' => 9.99,
            'description' => 'Customer loyalty rewards',
            'is_active' => true,
        ]);

        // Inactive (disabled) add-on
        $this->inactiveAddOn = PlanAddOn::create([
            'name' => 'Legacy Module',
            'name_ar' => 'وحدة قديمة',
            'slug' => 'legacy',
            'monthly_price' => 5.00,
            'description' => 'Deprecated',
            'is_active' => false,
        ]);

        // Free add-on (price = 0)
        $this->freeAddOn = PlanAddOn::create([
            'name' => 'Free Analytics',
            'name_ar' => 'تحليلات مجانية',
            'slug' => 'free-analytics',
            'monthly_price' => 0.00,
            'description' => 'Basic analytics at no cost',
            'is_active' => true,
        ]);
    }

    // ─── List Store Add-Ons ───────────────────────────────────────

    public function test_list_store_add_ons_returns_empty_when_none_activated(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_list_store_add_ons_includes_active_add_ons(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->addOn->id, $data[0]['plan_add_on_id']);
        $this->assertTrue($data[0]['is_active']);
    }

    public function test_list_store_add_ons_includes_nested_add_on_details(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $addOnData = $response->json('data.0.add_on');
        $this->assertNotNull($addOnData);
        $this->assertEquals('Loyalty Module', $addOnData['name']);
        $this->assertEquals('وحدة الولاء', $addOnData['name_ar']);
        $this->assertEquals('loyalty', $addOnData['slug']);
    }

    public function test_list_store_add_ons_includes_inactive_add_ons(): void
    {
        // Deactivated add-on still shows in list (with is_active = false)
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subDay(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertFalse($data[0]['is_active']);
    }

    public function test_list_store_add_ons_requires_store_id(): void
    {
        $userNoStore = User::create([
            'name' => 'No Store',
            'email' => 'nostore@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => null,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $tokenNoStore = $userNoStore->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($tokenNoStore)->getJson('/api/v2/subscription/store-add-ons');

        $response->assertNotFound();
    }

    // ─── Activate Add-On ─────────────────────────────────────────

    public function test_activate_add_on_creates_store_add_on_record(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_add_on_returns_add_on_details(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated()
            ->assertJsonPath('data.plan_add_on_id', $this->addOn->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.add_on.name', 'Loyalty Module')
            ->assertJsonPath('data.add_on.monthly_price', '9.99');
    }

    public function test_activate_add_on_sets_activated_at_timestamp(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated();
        $this->assertNotNull($response->json('data.activated_at'));
    }

    public function test_activate_already_active_add_on_returns_422(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_activate_nonexistent_add_on_returns_404(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/subscription/store-add-ons/00000000-0000-0000-0000-000000000000/activate');

        $response->assertNotFound();
    }

    public function test_activate_inactive_system_add_on_returns_404(): void
    {
        // $inactiveAddOn has is_active = false in plan_add_ons table
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->inactiveAddOn->id}/activate");

        $response->assertNotFound();
    }

    public function test_reactivate_previously_deactivated_add_on(): void
    {
        // First activate then deactivate
        $storeAddOn = StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subDay(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertCreated()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_free_add_on_succeeds(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->freeAddOn->id}/activate");

        $response->assertCreated()
            ->assertJsonPath('data.add_on.monthly_price', '0.00');
    }

    public function test_activate_add_on_requires_store_id(): void
    {
        $userNoStore = User::create([
            'name' => 'No Store User',
            'email' => 'nostore2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => null,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $tokenNoStore = $userNoStore->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($tokenNoStore)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertNotFound();
    }

    public function test_activate_add_on_requires_auth(): void
    {
        $response = $this->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate");

        $response->assertUnauthorized();
    }

    // ─── Remove / Deactivate Add-On ───────────────────────────────

    public function test_remove_active_add_on_deactivates_it(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertOk();

        $this->assertDatabaseHas('store_add_ons', [
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
        ]);
    }

    public function test_remove_add_on_sets_deactivated_at(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $storeAddOn = StoreAddOn::where('store_id', $this->store->id)
            ->where('plan_add_on_id', $this->addOn->id)
            ->first();

        $this->assertNotNull($storeAddOn->deactivated_at);
    }

    public function test_remove_already_inactive_add_on_returns_422(): void
    {
        StoreAddOn::create([
            'store_id' => $this->store->id,
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
            'activated_at' => now()->subDay(),
            'deactivated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_remove_nonexistent_add_on_returns_404(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/subscription/store-add-ons/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_remove_add_on_requires_auth(): void
    {
        $response = $this->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}");

        $response->assertUnauthorized();
    }

    // ─── Full Cycle ───────────────────────────────────────────────

    public function test_full_add_on_lifecycle_activate_deactivate_reactivate(): void
    {
        // 1. Activate
        $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate")
            ->assertCreated();

        // 2. Deactivate
        $this->withToken($this->token)
            ->deleteJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}")
            ->assertOk();

        $this->assertDatabaseHas('store_add_ons', [
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => false,
        ]);

        // 3. Reactivate
        $this->withToken($this->token)
            ->postJson("/api/v2/subscription/store-add-ons/{$this->addOn->id}/activate")
            ->assertCreated();

        $this->assertDatabaseHas('store_add_ons', [
            'plan_add_on_id' => $this->addOn->id,
            'is_active' => true,
        ]);
    }
}
