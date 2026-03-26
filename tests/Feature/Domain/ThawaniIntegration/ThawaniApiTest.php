<?php

namespace Tests\Feature\Domain\ThawaniIntegration;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThawaniApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@thawani-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Authentication ───────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/thawani/stats')->assertUnauthorized();
        $this->getJson('/api/v2/thawani/config')->assertUnauthorized();
    }

    // ─── Stats ────────────────────────────────────────────────────

    public function test_can_get_thawani_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/stats');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_stats_returns_correct_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_connected',
                    'total_orders',
                    'total_products_mapped',
                    'total_settlements',
                    'pending_orders',
                ],
            ]);
    }

    // ─── Config ───────────────────────────────────────────────────

    public function test_can_get_config(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/config');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_save_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', [
                'thawani_store_id'    => 'THAWANI-001',
                'auto_sync_products'  => true,
                'auto_sync_inventory' => false,
                'auto_accept_orders'  => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('thawani_store_config', [
            'store_id'            => $this->store->id,
            'thawani_store_id'    => 'THAWANI-001',
            'auto_sync_products'  => true,
            'auto_sync_inventory' => false,
        ]);
    }

    public function test_save_config_is_idempotent(): void
    {
        // Save once
        $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', ['thawani_store_id' => 'T-001', 'auto_sync_products' => true]);

        // Save again — should update, not create duplicate
        $this->withToken($this->token)
            ->postJson('/api/v2/thawani/config', ['thawani_store_id' => 'T-001', 'auto_sync_products' => false]);

        $this->assertEquals(1, ThawaniStoreConfig::where('store_id', $this->store->id)->count());
    }

    // ─── Disconnect ───────────────────────────────────────────────

    public function test_can_disconnect(): void
    {
        ThawaniStoreConfig::create([
            'store_id'         => $this->store->id,
            'thawani_store_id' => 'T-DISC-001',
            'is_connected'     => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/thawani/disconnect');

        $response->assertOk();

        $config = ThawaniStoreConfig::where('store_id', $this->store->id)->first();
        $this->assertFalse($config->is_connected);
    }

    public function test_disconnect_returns_404_when_not_connected(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/thawani/disconnect');

        $response->assertNotFound();
    }

    // ─── Orders ───────────────────────────────────────────────────

    public function test_can_list_thawani_orders(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/orders');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── Product Mappings ─────────────────────────────────────────

    public function test_can_list_product_mappings(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/product-mappings');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── Settlements ──────────────────────────────────────────────

    public function test_can_list_settlements(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/thawani/settlements');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }
}
