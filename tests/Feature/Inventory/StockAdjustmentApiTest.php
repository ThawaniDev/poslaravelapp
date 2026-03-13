<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentApiTest extends TestCase
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
            'email' => 'test@adj.com',
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

    // ─── Create Adjustment ────────────────────────────────────

    public function test_can_create_increase_adjustment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'increase',
                'reason_code' => 'recount',
                'notes' => 'Physical count found extra stock.',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 25],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'increase');

        // Check stock increased
        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($level);
        $this->assertEquals(25.00, (float) $level->quantity);
    }

    public function test_can_create_decrease_adjustment(): void
    {
        // First add stock
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'decrease',
                'reason_code' => 'damaged',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'decrease');

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertEquals(40.00, (float) $level->quantity);
    }

    public function test_create_adjustment_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'increase',
                'items' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_type_rejected(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'invalid',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ─── Show & List ──────────────────────────────────────────

    public function test_can_show_adjustment(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->store->id,
                'type' => 'increase',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/stock-adjustments/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_can_list_adjustments(): void
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
            ->getJson('/api/v2/inventory/stock-adjustments?store_id=' . $this->store->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }
}
