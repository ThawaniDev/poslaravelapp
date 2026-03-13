<?php

namespace Tests\Feature\Customer;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerGroup;
use App\Domain\Customer\Models\LoyaltyConfig;
use App\Domain\Customer\Models\LoyaltyTransaction;
use App\Domain\Customer\Models\StoreCreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@customer.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Customer CRUD ───────────────────────────────────────

    public function test_can_list_customers(): void
    {
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Alice',
            'phone' => '96812345678',
        ]);
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Bob',
            'phone' => '96887654321',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_can_search_customers(): void
    {
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Alice Wonder',
            'phone' => '96812345678',
        ]);
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Bob Builder',
            'phone' => '96887654321',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers?search=alice');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Alice Wonder', $response->json('data.data.0.name'));
    }

    public function test_can_create_customer(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/customers', [
                'name' => 'New Customer',
                'phone' => '96855551234',
                'email' => 'new@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Customer')
            ->assertJsonPath('data.phone', '96855551234');

        $this->assertNotNull($response->json('data.loyalty_code'));
    }

    public function test_phone_uniqueness_within_org(): void
    {
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Existing',
            'phone' => '96855551234',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/customers', [
                'name' => 'Duplicate',
                'phone' => '96855551234',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Me',
            'phone' => '96811112222',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show Me');
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/customers/{$customer->id}", [
                'name' => 'New Name',
                'phone' => '96899998888',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '96899998888');

        $this->assertEquals(2, $response->json('data.sync_version'));
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Me',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Customer deleted successfully.');

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // ─── Customer Groups ─────────────────────────────────────

    public function test_can_list_groups(): void
    {
        CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'VIP',
            'discount_percent' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/groups/list');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('VIP', $data[0]['name']);
    }

    public function test_can_create_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/customers/groups', [
                'name' => 'Wholesale',
                'discount_percent' => 15,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Wholesale');
        $this->assertEquals(15, $response->json('data.discount_percent'));
    }

    public function test_can_update_group(): void
    {
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Group',
            'discount_percent' => 5,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/customers/groups/{$group->id}", [
                'name' => 'Updated Group',
                'discount_percent' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Group');
    }

    public function test_cannot_delete_group_with_customers(): void
    {
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Has Members',
        ]);

        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Member',
            'group_id' => $group->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/customers/groups/{$group->id}");

        $response->assertStatus(422);
    }

    public function test_can_delete_empty_group(): void
    {
        $group = CustomerGroup::create([
            'organization_id' => $this->org->id,
            'name' => 'Empty Group',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/customers/groups/{$group->id}");

        $response->assertOk();
    }

    // ─── Loyalty ─────────────────────────────────────────────

    public function test_can_get_loyalty_config(): void
    {
        LoyaltyConfig::create([
            'organization_id' => $this->org->id,
            'points_per_sar' => 2,
            'sar_per_point' => 0.05,
            'min_redemption_points' => 200,
            'points_expiry_months' => 6,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/loyalty/config');

        $response->assertOk()
            ->assertJsonPath('data.min_redemption_points', 200)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_can_save_loyalty_config(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/customers/loyalty/config', [
                'points_per_sar' => 3,
                'min_redemption_points' => 50,
                'is_active' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.min_redemption_points', 50);
    }

    public function test_can_earn_loyalty_points(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Loyal Customer',
            'loyalty_points' => 100,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 50,
                'type' => 'earn',
                'notes' => 'Purchase #123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'earn')
            ->assertJsonPath('data.points', 50)
            ->assertJsonPath('data.balance_after', 150);

        $customer->refresh();
        $this->assertEquals(150, $customer->loyalty_points);
    }

    public function test_can_redeem_loyalty_points(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Loyal Customer',
            'loyalty_points' => 500,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 200,
                'type' => 'redeem',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'redeem')
            ->assertJsonPath('data.balance_after', 300);
    }

    public function test_cannot_redeem_more_than_balance(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Poor Customer',
            'loyalty_points' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$customer->id}/loyalty/adjust", [
                'points' => 500,
                'type' => 'redeem',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_get_loyalty_log(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Log Customer',
            'loyalty_points' => 100,
        ]);

        LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 100,
            'balance_after' => 100,
            'performed_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/customers/{$customer->id}/loyalty");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Store Credit ────────────────────────────────────────

    public function test_can_top_up_store_credit(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Credit Customer',
            'store_credit_balance' => 50.00,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$customer->id}/store-credit/top-up", [
                'amount' => 25.50,
                'notes' => 'Manual top-up',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'top_up');
        $this->assertEquals(75.50, $response->json('data.balance_after'));

        $customer->refresh();
        $this->assertEquals(75.50, (float) $customer->store_credit_balance);
    }

    public function test_can_get_store_credit_log(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Credit Log',
            'store_credit_balance' => 100,
        ]);

        StoreCreditTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'top_up',
            'amount' => 100,
            'balance_after' => 100,
            'performed_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/customers/{$customer->id}/store-credit");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/customers');
        $response->assertStatus(401);
    }
}
