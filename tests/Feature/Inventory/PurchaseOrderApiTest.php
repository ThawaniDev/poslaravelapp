<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;
    private Supplier $supplier;

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
            'email' => 'test@po.com',
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

    // ─── Create PO ───────────────────────────────────────────

    public function test_can_create_purchase_order(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'PO-001',
                'expected_date' => '2025-06-15',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 100, 'unit_cost' => 5.00],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');
        $this->assertEquals(500, $response->json('data.total_cost'));
    }

    public function test_create_po_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [],
            ]);

        $response->assertStatus(422);
    }

    // ─── Show & List ──────────────────────────────────────────

    public function test_can_show_purchase_order(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/purchase-orders/{$create->json('data.id')}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_can_list_purchase_orders(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/purchase-orders?store_id=' . $this->store->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Send PO ──────────────────────────────────────────────

    public function test_can_send_draft_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$create->json('data.id')}/send");

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_cannot_send_already_sent_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/send");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/send");

        $response->assertStatus(422);
    }

    // ─── Receive PO ───────────────────────────────────────────

    public function test_can_fully_receive_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $id = $create->json('data.id');

        // Send it first
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/send");

        // Fully receive
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/receive", [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_received' => 50],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'fully_received');
    }

    public function test_partial_receive_updates_status(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/send");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/receive", [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_received' => 20],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'partially_received');
    }

    public function test_cannot_receive_draft_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$create->json('data.id')}/receive", [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_received' => 50],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ─── Cancel PO ────────────────────────────────────────────

    public function test_can_cancel_draft_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$create->json('data.id')}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_received_po(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],
                ],
            ]);

        $id = $create->json('data.id');

        $this->withToken($this->token)->postJson("/api/v2/inventory/purchase-orders/{$id}/send");
        $this->withToken($this->token)->postJson("/api/v2/inventory/purchase-orders/{$id}/receive", [
            'items' => [['product_id' => $this->product->id, 'quantity_received' => 50]],
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/purchase-orders/{$id}/cancel");

        $response->assertStatus(422);
    }
}
