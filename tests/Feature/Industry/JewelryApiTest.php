<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JewelryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $org = Organization::create(['name' => 'Jewelry Org', 'slug' => 'jewelry-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Gold Shop', 'slug' => 'gold-shop-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Jeweler', 'email' => 'jeweler-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Gold', 'slug' => 'other-gold-' . uniqid(), 'is_active' => true]);
        $otherUser = User::create(['name' => 'Other Jeweler', 'email' => 'other-jeweler-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS daily_metal_rates');
        DB::statement('CREATE TABLE daily_metal_rates (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, metal_type VARCHAR(20) NOT NULL, karat VARCHAR(20), rate_per_gram DECIMAL(12,2) NOT NULL, buyback_rate_per_gram DECIMAL(12,2), effective_date DATE NOT NULL, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS jewelry_product_details');
        DB::statement('CREATE TABLE jewelry_product_details (id VARCHAR(36) PRIMARY KEY, product_id VARCHAR(36) NOT NULL, metal_type VARCHAR(20) NOT NULL, karat VARCHAR(20), gross_weight_g DECIMAL(10,2) NOT NULL, net_weight_g DECIMAL(10,2), making_charges_type VARCHAR(20) NOT NULL, making_charges_value DECIMAL(10,2) NOT NULL, stone_type VARCHAR(100), stone_weight_carat DECIMAL(8,2), stone_count INTEGER, certificate_number VARCHAR(100), certificate_url VARCHAR(500), created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS buyback_transactions');
        DB::statement('CREATE TABLE buyback_transactions (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, customer_id VARCHAR(36) NOT NULL, metal_type VARCHAR(20) NOT NULL, karat VARCHAR(20), weight_g DECIMAL(10,2) NOT NULL, rate_per_gram DECIMAL(12,2) NOT NULL, total_amount DECIMAL(12,2) NOT NULL, payment_method VARCHAR(30) NOT NULL, staff_user_id VARCHAR(36), notes TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/industry/jewelry/metal-rates')->assertUnauthorized();
    }

    // ── METAL RATES ─────────────────────────────────────

    public function test_list_metal_rates(): void
    {
        $this->postJson('/api/v2/industry/jewelry/metal-rates', [
            'metal_type' => 'gold', 'karat' => '24K', 'rate_per_gram' => 62.50, 'effective_date' => '2025-06-01',
        ], $this->h());

        $this->getJson('/api/v2/industry/jewelry/metal-rates', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_upsert_metal_rate(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/metal-rates', [
            'metal_type' => 'gold', 'karat' => '24K', 'rate_per_gram' => 62.50,
            'buyback_rate_per_gram' => 58.00, 'effective_date' => '2025-06-01',
        ], $this->h());
        $res->assertOk()->assertJsonPath('data.rate_per_gram', 62.50);
    }

    public function test_upsert_metal_rate_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/jewelry/metal-rates', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metal_type', 'rate_per_gram', 'effective_date']);
    }

    public function test_filter_metal_rates_by_metal_type(): void
    {
        $this->postJson('/api/v2/industry/jewelry/metal-rates', [
            'metal_type' => 'gold', 'karat' => '22K', 'rate_per_gram' => 55.00, 'effective_date' => '2025-06-01',
        ], $this->h());
        $this->postJson('/api/v2/industry/jewelry/metal-rates', [
            'metal_type' => 'silver', 'karat' => '925', 'rate_per_gram' => 0.85, 'effective_date' => '2025-06-01',
        ], $this->h());

        $this->getJson('/api/v2/industry/jewelry/metal-rates?metal_type=gold', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── PRODUCT DETAILS ─────────────────────────────────

    public function test_create_product_detail(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/product-details', [
            'product_id' => 'p-j1', 'metal_type' => 'gold', 'karat' => '22K',
            'gross_weight_g' => 10.5, 'net_weight_g' => 9.8,
            'making_charges_type' => 'per_gram', 'making_charges_value' => 3.50,
            'certificate_number' => 'CERT-001',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.metal_type', 'gold');
    }

    public function test_create_product_detail_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/jewelry/product-details', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'metal_type', 'gross_weight_g', 'making_charges_type', 'making_charges_value']);
    }

    public function test_update_product_detail(): void
    {
        $create = $this->postJson('/api/v2/industry/jewelry/product-details', [
            'product_id' => 'p-j2', 'metal_type' => 'silver', 'karat' => '925',
            'gross_weight_g' => 25.0, 'making_charges_type' => 'flat', 'making_charges_value' => 15.00,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/jewelry/product-details/{$id}", [
            'certificate_url' => 'https://example.com/cert.pdf',
        ], $this->h());
        $res->assertOk();
    }

    // ── BUYBACK TRANSACTIONS ────────────────────────────

    public function test_create_buyback(): void
    {
        $res = $this->postJson('/api/v2/industry/jewelry/buybacks', [
            'customer_id' => 'cust-j1', 'metal_type' => 'gold', 'karat' => '22K',
            'weight_g' => 5.0, 'rate_per_gram' => 58.00, 'total_amount' => 290.00, 'payment_method' => 'cash',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.total_amount', 290.00);
    }

    public function test_create_buyback_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/jewelry/buybacks', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'metal_type', 'weight_g', 'rate_per_gram', 'total_amount', 'payment_method']);
    }

    public function test_list_buybacks(): void
    {
        $this->postJson('/api/v2/industry/jewelry/buybacks', [
            'customer_id' => 'cust-j2', 'metal_type' => 'gold', 'weight_g' => 3.0,
            'rate_per_gram' => 60.00, 'total_amount' => 180.00, 'payment_method' => 'bank_transfer',
        ], $this->h());

        $this->getJson('/api/v2/industry/jewelry/buybacks', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_list_buybacks_only_shows_own_store(): void
    {
        $this->postJson('/api/v2/industry/jewelry/buybacks', [
            'customer_id' => 'cust-j3', 'metal_type' => 'silver', 'weight_g' => 10.0,
            'rate_per_gram' => 0.80, 'total_amount' => 8.00, 'payment_method' => 'cash',
        ], $this->h());

        $this->getJson('/api/v2/industry/jewelry/buybacks', $this->h($this->otherToken))
            ->assertOk()->assertJsonCount(0, 'data.data');
    }
}
