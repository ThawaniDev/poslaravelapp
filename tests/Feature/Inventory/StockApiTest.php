<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;

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
            'email' => 'test@stock.com',
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
    }

    // ─── Stock Levels ─────────────────────────────────────────

    public function test_can_list_stock_levels(): void
    {
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
;
        $this->assertEquals(50, $response->json('data.data.0.quantity'));
    }

    public function test_stock_levels_requires_store_id(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels');

        $response->assertStatus(422);
    }

    public function test_can_filter_low_stock(): void
    {
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        $product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Milk',
            'sell_price' => 2.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'average_cost' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id . '&low_stock=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_set_reorder_point(): void
    {
        $level = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/inventory/stock-levels/{$level->id}/reorder-point", [
                'reorder_point' => 15,
                'max_stock_level' => 200,
            ]);

        $response->assertOk();
        $this->assertEquals(15, $response->json('data.reorder_point'));
        $this->assertEquals(200, $response->json('data.max_stock_level'));
    }

    // ─── Stock Movements ──────────────────────────────────────

    public function test_can_list_stock_movements(): void
    {
        // Create a stock movement by doing an adjustment
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'increase',
                'reason_code' => 'recount',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-movements?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
    }

    public function test_can_filter_movements_by_product(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'increase',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-movements?store_id=' . $this->store->id . '&product_id=' . $this->product->id);

        $response->assertOk();
        foreach ($response->json('data.data') as $movement) {
            $this->assertEquals($this->product->id, $movement['product_id']);
        }
    }
}
