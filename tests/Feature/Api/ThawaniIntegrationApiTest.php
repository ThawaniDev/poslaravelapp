<?php

namespace Tests\Feature\Api;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Store;
use App\Domain\ThawaniIntegration\Models\ThawaniCategoryMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniColumnMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncLog;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncQueue;
use App\Domain\ThawaniIntegration\Services\ThawaniApiClient;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThawaniIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private string $base = '/api/v2/thawani';

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Thawani Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Thawani Test Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Thawani Test User',
            'email' => 'thawani@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // Seed default column mappings
        ThawaniColumnMapping::firstOrCreate(
            ['entity_type' => 'category', 'thawani_field' => 'name', 'wameed_field' => 'name'],
            ['transform_type' => 'json_extract', 'transform_config' => ['locale' => 'en']],
        );
        ThawaniColumnMapping::firstOrCreate(
            ['entity_type' => 'product', 'thawani_field' => 'name', 'wameed_field' => 'name'],
            ['transform_type' => 'json_extract', 'transform_config' => ['locale' => 'en']],
        );
        ThawaniColumnMapping::firstOrCreate(
            ['entity_type' => 'product', 'thawani_field' => 'price', 'wameed_field' => 'sell_price'],
            ['transform_type' => 'direct'],
        );
    }

    // ── Stats ───────────────────────────────────────────────
    public function test_stats_returns_all_fields(): void
    {
        ThawaniStoreConfig::create([
            'store_id' => $this->store->id,
            'thawani_store_id' => 'test-thawani-123',
            'is_connected' => true,
            'connected_at' => now(),
        ]);

        $r = $this->getJson("{$this->base}/stats");
        $r->assertOk()
            ->assertJsonStructure(['data' => [
                'is_connected',
                'thawani_store_id',
                'total_orders',
                'total_products_mapped',
                'total_categories_mapped',
                'total_settlements',
                'pending_orders',
                'pending_sync_items',
                'sync_logs_today',
                'failed_syncs_today',
            ]]);

        $this->assertTrue($r->json('data.is_connected'));
    }

    public function test_stats_returns_disconnected_when_no_config(): void
    {
        $r = $this->getJson("{$this->base}/stats");
        $r->assertOk();
        $this->assertFalse($r->json('data.is_connected'));
    }

    // ── Config ──────────────────────────────────────────────
    public function test_get_config(): void
    {
        ThawaniStoreConfig::create([
            'store_id' => $this->store->id,
            'thawani_store_id' => 'thawani-store',
            'auto_sync_products' => true,
        ]);

        $r = $this->getJson("{$this->base}/config");
        $r->assertOk();
    }

    public function test_save_config(): void
    {
        $r = $this->postJson("{$this->base}/config", [
            'thawani_store_id' => 'new-store-id',
            'auto_sync_products' => true,
            'auto_sync_inventory' => false,
            'commission_rate' => 5.5,
        ]);

        $r->assertOk();
        $this->assertDatabaseHas('thawani_store_config', [
            'store_id' => $this->store->id,
            'auto_sync_products' => true,
        ]);
    }

    public function test_save_config_validates_commission_rate(): void
    {
        $r = $this->postJson("{$this->base}/config", [
            'commission_rate' => 150,
        ]);

        $r->assertUnprocessable();
    }

    // ── Disconnect ──────────────────────────────────────────
    public function test_disconnect(): void
    {
        ThawaniStoreConfig::create([
            'store_id' => $this->store->id,
            'thawani_store_id' => 'store',
            'is_connected' => true,
        ]);

        $r = $this->putJson("{$this->base}/disconnect");
        $r->assertOk();

        $this->assertDatabaseHas('thawani_store_config', [
            'store_id' => $this->store->id,
            'is_connected' => false,
        ]);
    }

    public function test_disconnect_returns_404_when_no_config(): void
    {
        $r = $this->putJson("{$this->base}/disconnect");
        $r->assertNotFound();
    }

    // ── Test Connection ─────────────────────────────────────
    public function test_connection_fails_when_not_configured(): void
    {
        // No config => API credentials not set
        $r = $this->postJson("{$this->base}/test-connection");
        // Should return error since ThawaniApiClient is not configured
        $r->assertStatus(422);
    }

    // ── Product Mappings ────────────────────────────────────
    public function test_get_product_mappings(): void
    {
        $product = Product::create([
            'organization_id' => $this->store->organization_id ?? $this->store->id,
            'name' => 'Test Product',
            'name_ar' => 'منتج اختبار',
            'sell_price' => 10.00,
        ]);

        ThawaniProductMapping::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'thawani_product_id' => 999,
            'is_published' => true,
        ]);

        $r = $this->getJson("{$this->base}/product-mappings");
        $r->assertOk();
    }

    // ── Category Mappings ───────────────────────────────────
    public function test_get_category_mappings(): void
    {
        $category = Category::create([
            'organization_id' => $this->store->organization_id ?? $this->store->id,
            'name' => 'Test Category',
            'name_ar' => 'فئة اختبار',
            'is_active' => true,
        ]);

        ThawaniCategoryMapping::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'thawani_category_id' => 456,
            'sync_status' => 'synced',
            'sync_direction' => 'outgoing',
        ]);

        $r = $this->getJson("{$this->base}/category-mappings");
        $r->assertOk();
    }

    // ── Column Mappings ─────────────────────────────────────
    public function test_get_column_mappings(): void
    {
        $r = $this->getJson("{$this->base}/column-mappings");
        $r->assertOk();
    }

    public function test_seed_column_defaults(): void
    {
        $r = $this->postJson("{$this->base}/column-mappings/seed-defaults");
        $r->assertOk();

        $this->assertDatabaseHas('thawani_column_mappings', [
            'entity_type' => 'product',
            'thawani_field' => 'barcode',
            'wameed_field' => 'barcode',
        ]);
    }

    // ── Sync Logs ───────────────────────────────────────────
    public function test_get_sync_logs(): void
    {
        ThawaniSyncLog::create([
            'store_id' => $this->store->id,
            'entity_type' => 'product',
            'action' => 'post:products/sync',
            'direction' => 'outgoing',
            'status' => 'success',
            'completed_at' => now(),
        ]);

        $r = $this->getJson("{$this->base}/sync-logs");
        $r->assertOk();
    }

    public function test_get_sync_logs_with_filters(): void
    {
        ThawaniSyncLog::create([
            'store_id' => $this->store->id,
            'entity_type' => 'product',
            'action' => 'push',
            'direction' => 'outgoing',
            'status' => 'failed',
            'error_message' => 'Timeout',
            'completed_at' => now(),
        ]);

        $r = $this->getJson("{$this->base}/sync-logs?entity_type=product&status=failed");
        $r->assertOk();
    }

    public function test_sync_logs_validates_filters(): void
    {
        $r = $this->getJson("{$this->base}/sync-logs?entity_type=invalid");
        $r->assertUnprocessable();
    }

    // ── Queue Stats ─────────────────────────────────────────
    public function test_get_queue_stats(): void
    {
        ThawaniSyncQueue::create([
            'store_id' => $this->store->id,
            'entity_type' => 'product',
            'entity_id' => 'test-uuid',
            'action' => 'create',
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);

        $r = $this->getJson("{$this->base}/queue-stats");
        $r->assertOk()
            ->assertJsonStructure(['data' => [
                'pending', 'processing', 'completed', 'failed',
            ]]);

        $this->assertEquals(1, $r->json('data.pending'));
    }
}
