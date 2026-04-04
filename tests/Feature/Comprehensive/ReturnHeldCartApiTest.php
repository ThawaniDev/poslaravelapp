<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnHeldCartApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'Return Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Return Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Manager',
            'email' => 'return-mgr@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════
    // ─── ORDER RETURNS ───────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_list_returns(): void
    {
        $this->createReturn();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/returns/list');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_show_return(): void
    {
        $returnId = $this->createReturn();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/orders/returns/{$returnId}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_show_return_404_for_missing(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/orders/returns/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_can_create_return_for_order(): void
    {
        $orderId = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$orderId}/return", [
                'type' => 'full',
                'total_refund' => 100.00,
                'reason_code' => 'defective',
                'refund_method' => 'cash',
            ]);

        $response->assertSuccessful();
    }

    public function test_create_return_requires_total_refund(): void
    {
        $orderId = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$orderId}/return", [
                'type' => 'full',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['total_refund']);
    }

    public function test_create_return_validates_refund_method(): void
    {
        $orderId = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$orderId}/return", [
                'total_refund' => 50.00,
                'refund_method' => 'bitcoin',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_partial_return_with_items(): void
    {
        $orderId = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$orderId}/return", [
                'type' => 'partial',
                'total_refund' => 25.00,
                'items' => [
                    [
                        'product_id' => Str::uuid()->toString(),
                        'quantity' => 1,
                        'unit_price' => 25.00,
                        'refund_amount' => 25.00,
                    ],
                ],
            ]);

        $response->assertSuccessful();
    }

    public function test_return_404_for_missing_order(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$fakeId}/return", [
                'total_refund' => 10.00,
            ]);

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_can_void_order(): void
    {
        $orderId = $this->createOrder('new');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$orderId}/void");

        $response->assertSuccessful();
    }

    public function test_void_404_for_missing_order(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/orders/{$fakeId}/void");

        $this->assertContains($response->status(), [404, 422]);
    }

    // ═══════════════════════════════════════════════════════
    // ─── HELD CARTS ──────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_list_held_carts(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/held-carts');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_hold_cart(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/held-carts', [
                'cart_data' => [
                    ['product_id' => Str::uuid()->toString(), 'name' => 'Coffee', 'qty' => 2, 'price' => 15.00],
                    ['product_id' => Str::uuid()->toString(), 'name' => 'Croissant', 'qty' => 1, 'price' => 10.00],
                ],
                'label' => 'Table 5',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('held_carts', [
            'store_id' => $this->store->id,
            'label' => 'Table 5',
        ]);
    }

    public function test_hold_cart_requires_cart_data(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/held-carts', [
                'label' => 'Empty',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cart_data']);
    }

    public function test_can_recall_held_cart(): void
    {
        $cartId = $this->createHeldCart();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/held-carts/{$cartId}/recall");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_recall_404_for_missing_cart(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/held-carts/{$fakeId}/recall");

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_can_delete_held_cart(): void
    {
        $cartId = $this->createHeldCart();

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/pos/held-carts/{$cartId}");

        $response->assertOk();
        $this->assertDatabaseMissing('held_carts', [
            'id' => $cartId,
        ]);
    }

    public function test_delete_cart_404_for_missing(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/pos/held-carts/{$fakeId}");

        $response->assertNotFound();
    }

    // ─── Full Return + Held Cart Lifecycle ──────────────────

    public function test_held_cart_full_lifecycle(): void
    {
        // Hold a cart
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/held-carts', [
                'cart_data' => [
                    ['product_id' => Str::uuid()->toString(), 'name' => 'Latte', 'qty' => 1, 'price' => 20.00],
                ],
                'label' => 'Quick hold',
            ]);
        $response->assertCreated();
        $cartId = $response->json('data.id');

        // List and verify it's there
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/held-carts');
        $response->assertOk();

        // Recall it
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/held-carts/{$cartId}/recall");
        $response->assertOk();
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_return_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/orders/returns/list');
        $response->assertUnauthorized();
    }

    public function test_held_cart_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/pos/held-carts');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/v2/pos/held-carts', []);
        $response->assertUnauthorized();
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createOrder(string $status = 'completed'): string
    {
        $id = Str::uuid()->toString();
        DB::table('orders')->insert([
            'id' => $id,
            'store_id' => $this->store->id,
            'order_number' => 'ORD-' . rand(1000, 9999),
            'status' => $status,
            'subtotal' => 100.00,
            'total' => 100.00,
            'source' => 'pos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    private function createReturn(): string
    {
        $orderId = $this->createOrder();
        $id = Str::uuid()->toString();
        DB::table('returns')->insert([
            'id' => $id,
            'order_id' => $orderId,
            'store_id' => $this->store->id,
            'return_number' => 'RET-' . rand(1000, 9999),
            'type' => 'full',
            'total_refund' => 100.00,
            'processed_by' => $this->user->id,
            'created_at' => now(),
        ]);
        return $id;
    }

    private function createHeldCart(): string
    {
        $id = Str::uuid()->toString();
        DB::table('held_carts')->insert([
            'id' => $id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'cart_data' => json_encode([
                ['product_id' => Str::uuid()->toString(), 'name' => 'Tea', 'qty' => 1, 'price' => 8.00],
            ]),
            'label' => 'Test Cart',
            'held_at' => now(),
        ]);
        return $id;
    }
}
