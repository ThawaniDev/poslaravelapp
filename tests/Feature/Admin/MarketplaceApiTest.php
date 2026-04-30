<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Store;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $orgId;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Market Admin',
            'email'         => 'market@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->orgId = Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('organizations')->insert([
            'id'   => $this->orgId,
            'name' => 'Marketplace Org',
        ]);

        $this->store = Store::forceCreate([
            'name'            => 'Test Store',
            'organization_id' => $this->orgId,
        ]);
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/admin/marketplace/stores')->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════
    //  STORE LISTINGS
    // ═══════════════════════════════════════════════════════════

    public function test_list_marketplace_stores_empty(): void
    {
        $this->getJson('/api/v2/admin/marketplace/stores')
            ->assertOk()
            ->assertJsonPath('message', 'Marketplace stores retrieved')
            ->assertJsonPath('data.total', 0);
    }

    public function test_list_marketplace_stores(): void
    {
        $this->createStoreConfig();

        $this->getJson('/api/v2/admin/marketplace/stores')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_stores_by_connected(): void
    {
        $this->createStoreConfig(['is_connected' => true]);
        $s2 = Store::forceCreate(['name' => 'S2', 'organization_id' => $this->orgId]);
        $this->createStoreConfig(['store_id' => $s2->id, 'is_connected' => false]);

        $this->getJson('/api/v2/admin/marketplace/stores?is_connected=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_search_stores(): void
    {
        $this->createStoreConfig();

        $this->getJson('/api/v2/admin/marketplace/stores?search=Test+Store')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_store_config(): void
    {
        $config = $this->createStoreConfig();

        $this->getJson("/api/v2/admin/marketplace/stores/{$config->id}")
            ->assertOk()
            ->assertJsonPath('data.thawani_store_id', $config->thawani_store_id);
    }

    public function test_show_store_config_not_found(): void
    {
        $this->getJson('/api/v2/admin/marketplace/stores/00000000-0000-0000-0000-000000000099')
            ->assertNotFound();
    }

    public function test_update_store_config(): void
    {
        $config = $this->createStoreConfig(['commission_rate' => 5.00]);

        $this->putJson("/api/v2/admin/marketplace/stores/{$config->id}", [
            'commission_rate'    => 7.50,
            'auto_accept_orders' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.auto_accept_orders', true);

        $config->refresh();
        $this->assertEquals(7.50, (float) $config->commission_rate);
    }

    public function test_update_store_config_not_found(): void
    {
        $this->putJson('/api/v2/admin/marketplace/stores/00000000-0000-0000-0000-000000000099', ['commission_rate' => 1])
            ->assertNotFound();
    }

    public function test_connect_store(): void
    {
        $this->postJson("/api/v2/admin/marketplace/stores/{$this->store->id}/connect", [
            'auto_sync_products' => true,
            'commission_rate'    => 3.50,
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Store connected to marketplace');

        $this->assertDatabaseHas('thawani_store_config', [
            'store_id'     => $this->store->id,
            'is_connected' => true,
        ]);
    }

    public function test_connect_already_connected_store(): void
    {
        $this->createStoreConfig(['is_connected' => true]);

        $this->postJson("/api/v2/admin/marketplace/stores/{$this->store->id}/connect")
            ->assertStatus(422);
    }

    public function test_connect_store_not_found(): void
    {
        $this->postJson('/api/v2/admin/marketplace/stores/00000000-0000-0000-0000-000000000099/connect')
            ->assertNotFound();
    }

    public function test_disconnect_store(): void
    {
        $config = $this->createStoreConfig(['is_connected' => true]);

        $this->postJson("/api/v2/admin/marketplace/stores/{$config->id}/disconnect")
            ->assertOk()
            ->assertJsonPath('data.is_connected', false);
    }

    public function test_disconnect_store_not_found(): void
    {
        $this->postJson('/api/v2/admin/marketplace/stores/00000000-0000-0000-0000-000000000099/disconnect')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  PRODUCT LISTINGS
    // ═══════════════════════════════════════════════════════════

    public function test_list_marketplace_products_empty(): void
    {
        $this->getJson('/api/v2/admin/marketplace/products')
            ->assertOk()
            ->assertJsonPath('message', 'Marketplace products retrieved')
            ->assertJsonPath('data.total', 0);
    }

    public function test_list_marketplace_products(): void
    {
        $this->createProductMapping();

        $this->getJson('/api/v2/admin/marketplace/products')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_products_by_store(): void
    {
        $this->createProductMapping();

        $this->getJson("/api/v2/admin/marketplace/products?store_id={$this->store->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_products_by_published(): void
    {
        $this->createProductMapping(['is_published' => true]);
        $p2 = Product::forceCreate(['name' => 'P2', 'organization_id' => $this->orgId, 'sell_price' => 10]);
        $this->createProductMapping(['product_id' => $p2->id, 'is_published' => false]);

        $this->getJson('/api/v2/admin/marketplace/products?is_published=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_product_mapping(): void
    {
        $mapping = $this->createProductMapping();

        $this->getJson("/api/v2/admin/marketplace/products/{$mapping->id}")
            ->assertOk()
            ->assertJsonPath('data.thawani_product_id', $mapping->thawani_product_id);
    }

    public function test_show_product_mapping_not_found(): void
    {
        $this->getJson('/api/v2/admin/marketplace/products/00000000-0000-0000-0000-000000000099')
            ->assertNotFound();
    }

    public function test_update_product_listing(): void
    {
        $mapping = $this->createProductMapping(['online_price' => 10.00]);

        $this->putJson("/api/v2/admin/marketplace/products/{$mapping->id}", [
            'online_price'  => 12.50,
            'is_published'  => false,
            'display_order' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $mapping->refresh();
        $this->assertEquals(5, $mapping->display_order);
    }

    public function test_update_product_not_found(): void
    {
        $this->putJson('/api/v2/admin/marketplace/products/00000000-0000-0000-0000-000000000099', ['online_price' => 1])
            ->assertNotFound();
    }

    public function test_bulk_publish(): void
    {
        $m1 = $this->createProductMapping(['is_published' => false]);
        $p2 = Product::forceCreate(['name' => 'P2', 'organization_id' => $this->orgId, 'sell_price' => 10]);
        $m2 = $this->createProductMapping(['product_id' => $p2->id, 'is_published' => false]);

        $this->postJson('/api/v2/admin/marketplace/products/bulk-publish', [
            'product_ids'  => [$m1->id, $m2->id],
            'is_published' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2);

        $m1->refresh();
        $this->assertTrue((bool) $m1->is_published);
    }

    public function test_bulk_publish_validation(): void
    {
        $this->postJson('/api/v2/admin/marketplace/products/bulk-publish', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_ids', 'is_published']);
    }

    // ═══════════════════════════════════════════════════════════
    //  ORDERS
    // ═══════════════════════════════════════════════════════════

    public function test_list_orders_empty(): void
    {
        $this->getJson('/api/v2/admin/marketplace/orders')
            ->assertOk()
            ->assertJsonPath('message', 'Marketplace orders retrieved')
            ->assertJsonPath('data.total', 0);
    }

    public function test_list_orders(): void
    {
        $this->createOrderMapping();

        $this->getJson('/api/v2/admin/marketplace/orders')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_orders_by_status(): void
    {
        $this->createOrderMapping(['status' => 'new']);
        $this->createOrderMapping(['status' => 'completed', 'completed_at' => now()]);

        $this->getJson('/api/v2/admin/marketplace/orders?status=completed')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_orders_by_delivery_type(): void
    {
        $this->createOrderMapping(['delivery_type' => 'delivery']);
        $this->createOrderMapping(['delivery_type' => 'pickup']);

        $this->getJson('/api/v2/admin/marketplace/orders?delivery_type=pickup')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_search_orders(): void
    {
        $this->createOrderMapping(['customer_name' => 'Ahmed']);
        $this->createOrderMapping(['customer_name' => 'Sara']);

        $this->getJson('/api/v2/admin/marketplace/orders?search=Ahmed')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_order(): void
    {
        $order = $this->createOrderMapping();

        $this->getJson("/api/v2/admin/marketplace/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.thawani_order_number', $order->thawani_order_number);
    }

    public function test_show_order_not_found(): void
    {
        $this->getJson('/api/v2/admin/marketplace/orders/00000000-0000-0000-0000-000000000099')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  SETTLEMENTS
    // ═══════════════════════════════════════════════════════════

    public function test_list_settlements_empty(): void
    {
        $this->getJson('/api/v2/admin/marketplace/settlements')
            ->assertOk()
            ->assertJsonPath('message', 'Settlements retrieved')
            ->assertJsonPath('data.total', 0);
    }

    public function test_list_settlements(): void
    {
        $this->createSettlement();

        $this->getJson('/api/v2/admin/marketplace/settlements')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_filter_settlements_by_date(): void
    {
        $this->createSettlement(['settlement_date' => '2025-06-01']);
        $this->createSettlement(['settlement_date' => '2025-07-01']);

        $this->getJson('/api/v2/admin/marketplace/settlements?date_from=2025-06-15')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_show_settlement(): void
    {
        $settlement = $this->createSettlement();

        $this->getJson("/api/v2/admin/marketplace/settlements/{$settlement->id}")
            ->assertOk()
            ->assertJsonPath('data.order_count', $settlement->order_count);
    }

    public function test_show_settlement_not_found(): void
    {
        $this->getJson('/api/v2/admin/marketplace/settlements/00000000-0000-0000-0000-000000000099')
            ->assertNotFound();
    }

    public function test_settlement_summary(): void
    {
        $this->createSettlement(['gross_amount' => 100, 'commission_amount' => 5, 'net_amount' => 95, 'order_count' => 10]);
        $this->createSettlement(['gross_amount' => 200, 'commission_amount' => 10, 'net_amount' => 190, 'order_count' => 20]);

        $response = $this->getJson('/api/v2/admin/marketplace/settlements/summary')
            ->assertOk()
            ->assertJsonPath('message', 'Settlement summary retrieved');

        $data = $response->json('data');
        $this->assertEquals(300, (float) $data['total_gross']);
        $this->assertEquals(15, (float) $data['total_commission']);
        $this->assertEquals(285, (float) $data['total_net']);
        $this->assertEquals(30, $data['total_orders']);
        $this->assertEquals(2, $data['settlement_count']);
    }

    public function test_settlement_summary_by_store(): void
    {
        $s2 = Store::forceCreate(['name' => 'S2', 'organization_id' => $this->orgId]);
        $this->createSettlement(['gross_amount' => 100, 'commission_amount' => 5, 'net_amount' => 95, 'order_count' => 10]);
        $this->createSettlement(['store_id' => $s2->id, 'gross_amount' => 200, 'commission_amount' => 10, 'net_amount' => 190, 'order_count' => 20]);

        $response = $this->getJson("/api/v2/admin/marketplace/settlements/summary?store_id={$this->store->id}")
            ->assertOk();

        $this->assertEquals(100, (float) $response->json('data.total_gross'));
    }

    // ═══════════════════════════════════════════════════════════
    //  PAGINATION
    // ═══════════════════════════════════════════════════════════

    public function test_stores_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $s = Store::forceCreate(['name' => "S$i", 'organization_id' => $this->orgId]);
            $this->createStoreConfig(['store_id' => $s->id]);
        }

        $this->getJson('/api/v2/admin/marketplace/stores?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createStoreConfig(array $overrides = []): ThawaniStoreConfig
    {
        return ThawaniStoreConfig::forceCreate(array_merge([
            'store_id'           => $this->store->id,
            'thawani_store_id'   => 'TH-' . strtoupper(Str::random(8)),
            'is_connected'       => true,
            'auto_sync_products' => true,
            'auto_sync_inventory' => true,
            'auto_accept_orders' => false,
            'commission_rate'    => 5.00,
            'connected_at'       => now(),
        ], $overrides));
    }

    private function createProductMapping(array $overrides = []): ThawaniProductMapping
    {
        if (! isset($overrides['product_id'])) {
            $product = Product::forceCreate([
                'name'            => 'Test Product ' . Str::random(4),
                'organization_id' => $this->orgId,
                'sell_price'      => 25.00,
            ]);
            $overrides['product_id'] = $product->id;
        }

        return ThawaniProductMapping::forceCreate(array_merge([
            'store_id'           => $this->store->id,
            'product_id'         => $overrides['product_id'],
            'thawani_product_id' => 'TP-' . strtoupper(Str::random(8)),
            'is_published'       => true,
            'online_price'       => 25.00,
            'display_order'      => 0,
        ], $overrides));
    }

    private function createOrderMapping(array $overrides = []): ThawaniOrderMapping
    {
        return ThawaniOrderMapping::forceCreate(array_merge([
            'store_id'             => $this->store->id,
            'thawani_order_id'     => 'TO-' . strtoupper(Str::random(8)),
            'thawani_order_number' => 'ORD-' . strtoupper(Str::random(6)),
            'status'               => 'new',
            'delivery_type'        => 'delivery',
            'customer_name'        => 'Test Customer',
            'customer_phone'       => '0501234567',
            'order_total'          => 50.00,
        ], $overrides));
    }

    private function createSettlement(array $overrides = []): ThawaniSettlement
    {
        return ThawaniSettlement::forceCreate(array_merge([
            'store_id'          => $this->store->id,
            'settlement_date'   => now()->toDateString(),
            'gross_amount'      => 1000.00,
            'commission_amount' => 50.00,
            'net_amount'        => 950.00,
            'order_count'       => 25,
        ], $overrides));
    }
}
