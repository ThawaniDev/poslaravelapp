<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $storeA;
    private Store $storeB;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->storeA = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Store A',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->storeB = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Store B',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@transfer.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeA->id,
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

        // Pre-stock store A
        StockLevel::create([
            'store_id' => $this->storeA->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);
    }

    // ─── Create Transfer ──────────────────────────────────────

    public function test_can_create_transfer(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'reference_number' => 'TR-001',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 30],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_transfer_from_and_to_must_differ(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeA->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ─── Show & List ──────────────────────────────────────────

    public function test_can_show_transfer(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 20],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/stock-transfers/{$create->json('data.id')}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_can_list_transfers(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 20],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-transfers');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Approve Transfer ─────────────────────────────────────

    public function test_can_approve_transfer_deducts_from_source(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 30],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$create->json('data.id')}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        // Source stock should be reduced: 100 - 30 = 70
        $level = StockLevel::where('store_id', $this->storeA->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(70.00, (float) $level->quantity);
    }

    public function test_cannot_approve_non_pending_transfer(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 10],
                ],
            ]);

        $id = $create->json('data.id');

        // Approve once
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/approve")
            ->assertOk();

        // Try approve again
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/approve");

        $response->assertStatus(422);
    }

    // ─── Receive Transfer ─────────────────────────────────────

    public function test_can_receive_transfer_adds_to_destination(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 30],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/approve");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/receive");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Destination stock should now have 30
        $levelB = StockLevel::where('store_id', $this->storeB->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNotNull($levelB);
        $this->assertEquals(30.00, (float) $levelB->quantity);
    }

    public function test_can_receive_with_variance(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 30],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/approve");

        // Receive only 28 (2 lost in transit)
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/receive", [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_received' => 28],
                ],
            ]);

        $response->assertOk();

        $levelB = StockLevel::where('store_id', $this->storeB->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(28.00, (float) $levelB->quantity);
    }

    // ─── Cancel Transfer ──────────────────────────────────────

    public function test_can_cancel_pending_transfer(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 10],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$create->json('data.id')}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_in_transit_transfer(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_sent' => 10],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/approve");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/stock-transfers/{$id}/cancel");

        $response->assertStatus(422);
    }
}
