<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\Stocktake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StocktakeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;
    private Product $product2;

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
            'email' => 'test@stocktake.com',
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

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Tea Leaves',
            'sell_price' => 8.00,
            'sync_version' => 1,
        ]);

        // Create stock levels for both products
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reorder_point' => 10,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product2->id,
            'quantity' => 30,
            'reorder_point' => 5,
            'average_cost' => 3.00,
            'sync_version' => 1,
        ]);
    }

    public function test_can_create_full_stocktake(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
                'notes' => 'Monthly full count',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'full')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.notes', 'Monthly full count');

        // Full stocktake should pre-populate items from all stock levels
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_can_create_category_stocktake(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Beverages',
        ]);

        $this->product->update(['category_id' => $category->id]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'category',
                'category_id' => $category->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'category');

        // Only the product in this category should be included
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_can_list_stocktakes(): void
    {
        // Create a stocktake first
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stocktakes?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
    }

    public function test_can_show_stocktake(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stocktakes/' . $stocktakeId);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $stocktakeId);
    }

    public function test_can_update_stocktake_counts(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'counted_qty' => 48,
                        'notes' => '2 missing',
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'counted_qty' => 30,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Both items counted → should transition to review
        $this->assertEquals('review', $response->json('data.status'));

        // Check variance calculated
        $items = collect($response->json('data.items'));
        $coffeeItem = $items->firstWhere('product_id', $this->product->id);
        $this->assertEquals(-2, $coffeeItem['variance']);

        $teaItem = $items->firstWhere('product_id', $this->product2->id);
        $this->assertEquals(0, $teaItem['variance']);
    }

    public function test_can_apply_stocktake(): void
    {
        // Create stocktake
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        // Update counts: coffee has 48 (was 50), tea has 32 (was 30)
        $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    ['product_id' => $this->product->id, 'counted_qty' => 48],
                    ['product_id' => $this->product2->id, 'counted_qty' => 32],
                ],
            ]);

        // Apply
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/apply');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        // Verify stock was adjusted
        $coffeeStock = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(48, (float) $coffeeStock->quantity);

        $teaStock = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product2->id)
            ->first();
        $this->assertEquals(32, (float) $teaStock->quantity);
    }

    public function test_can_cancel_stocktake(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/cancel');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_completed_stocktake(): void
    {
        // Create and complete a stocktake
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    ['product_id' => $this->product->id, 'counted_qty' => 50],
                    ['product_id' => $this->product2->id, 'counted_qty' => 30],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/apply');

        // Try to cancel after completion
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/cancel');

        $response->assertStatus(422);
    }

    public function test_cannot_update_counts_on_completed_stocktake(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    ['product_id' => $this->product->id, 'counted_qty' => 50],
                    ['product_id' => $this->product2->id, 'counted_qty' => 30],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/apply');

        // Try to update counts after completion
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    ['product_id' => $this->product->id, 'counted_qty' => 99],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_stocktake_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'type']);
    }

    public function test_stocktake_discovered_item(): void
    {
        // Product3 is NOT in stock levels — it's "discovered" during count
        $product3 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Discovered Item',
            'sell_price' => 15.00,
            'sync_version' => 1,
        ]);

        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->store->id,
                'type' => 'full',
            ]);

        $stocktakeId = $createResponse->json('data.id');

        // Count all expected + discovered item
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/inventory/stocktakes/' . $stocktakeId . '/counts', [
                'items' => [
                    ['product_id' => $this->product->id, 'counted_qty' => 50],
                    ['product_id' => $this->product2->id, 'counted_qty' => 30],
                    ['product_id' => $product3->id, 'counted_qty' => 5],
                ],
            ]);

        $response->assertOk();

        // The discovered item should have expected_qty = 0, variance = 5
        $items = collect($response->json('data.items'));
        $discovered = $items->firstWhere('product_id', $product3->id);
        $this->assertNotNull($discovered);
        $this->assertEquals(0, (float) $discovered['expected_qty']);
        $this->assertEquals(5, (float) $discovered['variance']);
    }
}
