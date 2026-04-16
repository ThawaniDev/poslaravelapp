<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThawaniIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'Thawani Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Thawani Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'thawani-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════
    // ─── CONFIG ──────────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_get_thawani_config(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/config');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_save_thawani_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', [
                'thawani_store_id' => 'THAWANI-STORE-123',
                'is_connected' => true,
                'auto_sync_products' => true,
                'auto_sync_inventory' => false,
                'auto_accept_orders' => true,
                'commission_rate' => 5.5,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_save_config_validates_commission_rate_range(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', [
                'commission_rate' => 150, // max:100
            ]);

        $response->assertUnprocessable();
    }

    public function test_can_save_partial_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', [
                'auto_accept_orders' => false,
            ]);

        $response->assertOk();
    }

    public function test_can_disconnect_thawani(): void
    {
        // First save a config
        $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', [
                'thawani_store_id' => 'DISC-001',
                'is_connected' => true,
            ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/thawani/disconnect');

        $response->assertSuccessful();
    }

    // ═══════════════════════════════════════════════════════
    // ─── ORDERS ──────────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_list_thawani_orders(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/orders');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_filter_thawani_orders_by_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/orders?status=pending');

        $response->assertOk();
    }

    public function test_orders_per_page_validates_range(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/orders?per_page=100');

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════
    // ─── PRODUCT MAPPINGS ────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_get_product_mappings(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/product-mappings');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ═══════════════════════════════════════════════════════
    // ─── SETTLEMENTS ─────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_get_thawani_settlements(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/settlements');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ═══════════════════════════════════════════════════════
    // ─── STATS ───────────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_get_thawani_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/stats');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_thawani_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/thawani/config');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/thawani/orders');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/thawani/stats');
        $response->assertUnauthorized();
    }
}
