<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\GoodsReceiptItem;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;
    private ?Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@gr.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee Beans',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Bean Corp',
            'is_active' => true,
        ]);
    }

    // ─── Create Goods Receipt ─────────────────────────────────

    public function test_can_create_goods_receipt_draft(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'GR-001',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 100,
                        'unit_cost' => 5.00,
                        'batch_number' => 'BATCH-001',
                        'expiry_date' => '2025-12-31',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reference_number', 'GR-001');
    }

    public function test_create_goods_receipt_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [],
            ]);

        $response->assertStatus(422);
    }

    // ─── Show Goods Receipt ───────────────────────────────────

    public function test_can_show_goods_receipt(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/goods-receipts/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonCount(1, 'data.items');
    }

    // ─── List Goods Receipts ──────────────────────────────────

    public function test_can_list_goods_receipts(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/goods-receipts?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Confirm Goods Receipt ────────────────────────────────

    public function test_can_confirm_goods_receipt_updates_stock(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 100,
                        'unit_cost' => 5.00,
                        'batch_number' => 'BATCH-001',
                    ],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/goods-receipts/{$id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // Verify stock was updated
        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($level);
        $this->assertEquals(100.00, (float) $level->quantity);
        $this->assertEquals(5.00, (float) $level->average_cost);

        // Verify batch was created
        $batch = StockBatch::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($batch);
        $this->assertEquals('BATCH-001', $batch->batch_number);
    }

    public function test_cannot_confirm_already_confirmed_receipt(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 1.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        // Confirm first time
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/goods-receipts/{$id}/confirm")
            ->assertOk();

        // Second confirm should fail
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/goods-receipts/{$id}/confirm");

        $response->assertStatus(422);
    }

    public function test_wac_calculation_on_multiple_receipts(): void
    {
        // First receipt: 100 units @ 5.00
        $r1 = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 100, 'unit_cost' => 5.00],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/goods-receipts/{$r1->json('data.id')}/confirm");

        // Second receipt: 50 units @ 8.00
        $r2 = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 8.00],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/goods-receipts/{$r2->json('data.id')}/confirm");

        // WAC should be (100*5 + 50*8) / 150 = 900/150 = 6.00
        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertEquals(150.00, (float) $level->quantity);
        $this->assertEquals(6.00, (float) $level->average_cost);
    }
}
