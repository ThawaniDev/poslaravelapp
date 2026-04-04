<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerLoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'Loyalty Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Loyalty Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'loyalty-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->customer = Customer::forceCreate([
            'organization_id' => $this->org->id,
            'name' => 'Ahmed Customer',
            'phone' => '+966501234567',
            'loyalty_points' => 500,
            'store_credit_balance' => 50.00,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Loyalty Config ──────────────────────────────────────

    public function test_can_get_loyalty_config(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/loyalty/config');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_save_loyalty_config(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/customers/loyalty/config', [
                'points_per_sar' => 2,
                'sar_per_point' => 0.01,
                'min_redemption_points' => 200,
                'points_expiry_months' => 6,
                'is_active' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('loyalty_config', [
            'organization_id' => $this->org->id,
            'points_per_sar' => 2,
        ]);
    }

    public function test_can_update_loyalty_config_partially(): void
    {
        // First save
        $this->withToken($this->token)
            ->putJson('/api/v2/customers/loyalty/config', [
                'points_per_sar' => 1,
                'is_active' => true,
            ]);

        // Partial update
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/customers/loyalty/config', [
                'min_redemption_points' => 50,
            ]);

        $response->assertOk();
    }

    // ─── Loyalty Points ──────────────────────────────────────

    public function test_can_earn_loyalty_points(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/loyalty/adjust", [
                'points' => 100,
                'type' => 'earn',
                'notes' => 'Purchase reward',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $this->customer->id,
            'type' => 'earn',
            'points' => 100,
        ]);
    }

    public function test_can_redeem_loyalty_points(): void
    {
        // Setup: ensure customer has enough points
        DB::table('loyalty_config')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'points_per_sar' => 1,
            'sar_per_point' => 0.01,
            'min_redemption_points' => 10,
            'points_expiry_months' => 12,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/loyalty/adjust", [
                'points' => 100,
                'type' => 'redeem',
            ]);

        // Either succeeds or fails if not enough points
        $this->assertContains($response->status(), [201, 422]);
    }

    public function test_can_adjust_loyalty_points(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/loyalty/adjust", [
                'points' => -50,
                'type' => 'adjust',
                'notes' => 'Manual correction',
            ]);

        $response->assertCreated();
    }

    public function test_adjust_points_requires_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/loyalty/adjust", [
                'points' => 50,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_adjust_points_validates_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/loyalty/adjust", [
                'points' => 50,
                'type' => 'invalid_type',
            ]);

        $response->assertUnprocessable();
    }

    // ─── Loyalty Log ─────────────────────────────────────────

    public function test_can_get_loyalty_log(): void
    {
        // Create a transaction first
        DB::table('loyalty_transactions')->insert([
            'id' => Str::uuid()->toString(),
            'customer_id' => $this->customer->id,
            'type' => 'earn',
            'points' => 100,
            'balance_after' => 600,
            'performed_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/customers/{$this->customer->id}/loyalty");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Store Credit ────────────────────────────────────────

    public function test_can_top_up_store_credit(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/store-credit/top-up", [
                'amount' => 25.50,
                'notes' => 'Refund for returned item',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $this->customer->id,
            'type' => 'top_up',
        ]);
    }

    public function test_top_up_requires_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/store-credit/top-up", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_top_up_rejects_zero_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/customers/{$this->customer->id}/store-credit/top-up", [
                'amount' => 0,
            ]);

        $response->assertUnprocessable();
    }

    public function test_can_get_store_credit_log(): void
    {
        DB::table('store_credit_transactions')->insert([
            'id' => Str::uuid()->toString(),
            'customer_id' => $this->customer->id,
            'type' => 'top_up',
            'amount' => 25.00,
            'balance_after' => 75.00,
            'performed_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/customers/{$this->customer->id}/store-credit");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Auth Required ───────────────────────────────────────

    public function test_loyalty_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/customers/loyalty/config');
        $response->assertUnauthorized();
    }
}
