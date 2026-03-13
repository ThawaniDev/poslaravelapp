<?php

namespace Tests\Feature\Order;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\Models\SaleReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

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
            'email' => 'test@orders.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Orders ──────────────────────────────────────────────

    public function test_can_create_order(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/orders', [
                'subtotal' => 80.00,
                'tax_amount' => 12.00,
                'total' => 92.00,
                'items' => [
                    [
                        'product_name' => 'Widget',
                        'quantity' => 2,
                        'unit_price' => 40.00,
                        'total' => 80.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'new');

        $this->assertStringStartsWith('ORD-', $response->json('data.order_number'));
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_create_order_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/orders', [
                'subtotal' => 10,
                'total' => 10,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_orders(): void
    {
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0001',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'discount_amount' => 0,
            'total' => 57.5,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_show_order(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0002',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'discount_amount' => 0,
            'total' => 57.5,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.order_number', 'ORD-20250101-0002');
    }

    public function test_can_update_status(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0003',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'discount_amount' => 0,
            'total' => 57.5,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", [
                'status' => 'preparing',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'preparing');
    }

    public function test_invalid_status_transition(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0004',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'discount_amount' => 0,
            'total' => 57.5,
            'created_by' => $this->user->id,
        ]);

        // Can't go directly from New to Delivered
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", [
                'status' => 'delivered',
            ]);

        $response->assertStatus(422);
    }

    public function test_full_status_flow(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0005',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 50,
            'created_by' => $this->user->id,
        ]);

        // new -> preparing -> ready -> picked_up -> completed
        $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk();

        $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertOk();

        $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", ['status' => 'picked_up'])
            ->assertOk();

        $this->withToken($this->token)
            ->putJson("/api/v2/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_can_void_order(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0006',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::New->value,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 50,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$order->id}/void");

        $response->assertOk()
            ->assertJsonPath('data.status', 'voided');
    }

    public function test_cannot_void_completed_order(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0007',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::Completed->value,
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 50,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$order->id}/void");

        $response->assertStatus(422);
    }

    // ─── Returns ─────────────────────────────────────────────

    public function test_can_create_return(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0010',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::Completed->value,
            'subtotal' => 100,
            'tax_amount' => 15,
            'discount_amount' => 0,
            'total' => 115,
            'created_by' => $this->user->id,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Widget',
            'quantity' => 2,
            'unit_price' => 50,
            'discount_amount' => 0,
            'tax_amount' => 7.5,
            'total' => 100,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$order->id}/return", [
                'type' => 'partial',
                'reason_code' => 'defective',
                'refund_method' => 'cash',
                'total_refund' => 57.50,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'quantity' => 1,
                        'unit_price' => 50,
                        'refund_amount' => 57.50,
                    ],
                ],
            ]);

        // Debug: dump response if not 201
        $response->assertStatus(201);
        $this->assertStringStartsWith('RTN-', $response->json('data.return_number'));
        $this->assertEquals('partial', $response->json('data.type'));
    }

    public function test_can_list_returns(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-20250101-0011',
            'source' => OrderSource::Pos->value,
            'status' => OrderStatus::Completed->value,
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        SaleReturn::create([
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'return_number' => 'RTN-20250101-0001',
            'type' => 'full',
            'refund_method' => 'cash',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_refund' => 100,
            'processed_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/returns/list');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v2/orders')->assertStatus(401);
    }
}
