<?php

namespace Tests\Feature\Debit;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Debit\Enums\DebitSource;
use App\Domain\Debit\Enums\DebitStatus;
use App\Domain\Debit\Enums\DebitType;
use App\Domain\Debit\Models\Debit;
use App\Domain\Debit\Models\DebitAllocation;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebitApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private Customer $customer;
    private string $token;

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
            'email' => 'test@debits.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'John Doe',
            'phone' => '+96812345678',
            'email' => 'john@example.com',
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Create Debit ───────────────────────────────────────────

    public function test_can_create_debit(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'manual',
                'amount' => 150.50,
                'description' => 'Test credit',
                'description_ar' => 'رصيد تجريبي',
                'reference_number' => 'REF-001',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', 150.50)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.debit_type', 'customer_credit')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.remaining_balance', 150.50)
            ->assertJsonPath('data.reference_number', 'REF-001')
            ->assertJsonPath('data.description', 'Test credit')
            ->assertJsonPath('data.description_ar', 'رصيد تجريبي');

        $this->assertDatabaseHas('debits', [
            'customer_id' => $this->customer->id,
            'amount' => '150.50',
            'status' => 'pending',
        ]);
    }

    public function test_create_debit_requires_customer(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'debit_type' => 'customer_credit',
                'source' => 'manual',
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_create_debit_requires_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'manual',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_debit_validates_minimum_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'manual',
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_debit_validates_maximum_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'manual',
                'amount' => 1000000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_debit_validates_debit_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'invalid_type',
                'source' => 'manual',
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['debit_type']);
    }

    public function test_create_debit_validates_source(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => $this->customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'invalid_source',
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_create_debit_validates_customer_exists(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/debits', [
                'customer_id' => '00000000-0000-0000-0000-000000000000',
                'debit_type' => 'customer_credit',
                'source' => 'manual',
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_create_debit_with_all_types(): void
    {
        foreach (DebitType::cases() as $type) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/debits', [
                    'customer_id' => $this->customer->id,
                    'debit_type' => $type->value,
                    'source' => 'manual',
                    'amount' => 50,
                ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.debit_type', $type->value);
        }
    }

    public function test_create_debit_with_all_sources(): void
    {
        foreach (DebitSource::cases() as $source) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/debits', [
                    'customer_id' => $this->customer->id,
                    'debit_type' => 'customer_credit',
                    'source' => $source->value,
                    'amount' => 50,
                ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.source', $source->value);
        }
    }

    // ─── List Debits ────────────────────────────────────────────

    public function test_can_list_debits(): void
    {
        $this->createDebit(['amount' => 100]);
        $this->createDebit(['amount' => 200]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_list_debits_with_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createDebit(['amount' => ($i + 1) * 10]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
    }

    public function test_list_debits_filter_by_status(): void
    {
        $this->createDebit(['amount' => 100, 'status' => DebitStatus::Pending->value]);
        $this->createDebit(['amount' => 200, 'status' => DebitStatus::Reversed->value]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(100, $response->json('data.data.0.amount'));
    }

    public function test_list_debits_filter_by_customer(): void
    {
        $otherCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Customer',
            'phone' => '+96899887766',
        ]);

        $this->createDebit(['amount' => 100, 'customer_id' => $this->customer->id]);
        $this->createDebit(['amount' => 200, 'customer_id' => $otherCustomer->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits?customer_id=' . $this->customer->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_debits_filter_by_debit_type(): void
    {
        $this->createDebit(['debit_type' => 'customer_credit']);
        $this->createDebit(['debit_type' => 'supplier_return']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits?debit_type=customer_credit');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_debits_search(): void
    {
        $this->createDebit(['reference_number' => 'REF-FIND-ME']);
        $this->createDebit(['reference_number' => 'REF-OTHER']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits?search=FIND-ME');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_debits_scoped_by_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'fashion',
            'country' => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'fashion',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        // Create debit in other org
        Debit::create([
            'organization_id' => $otherOrg->id,
            'store_id' => $otherStore->id,
            'customer_id' => $this->customer->id,
            'debit_type' => 'customer_credit',
            'source' => 'manual',
            'amount' => 999,
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(100, $response->json('data.data.0.amount'));
    }

    // ─── Show Debit ─────────────────────────────────────────────

    public function test_can_show_debit(): void
    {
        $debit = $this->createDebit([
            'amount' => 250.75,
            'description' => 'Show test',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/{$debit->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $debit->id)
            ->assertJsonPath('data.amount', 250.75)
            ->assertJsonPath('data.description', 'Show test')
            ->assertJsonPath('data.customer.name', 'John Doe');
    }

    public function test_show_debit_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    // ─── Update Debit ───────────────────────────────────────────

    public function test_can_update_debit(): void
    {
        $debit = $this->createDebit(['amount' => 100, 'description' => 'Old']);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/debits/{$debit->id}", [
                'description' => 'Updated description',
                'notes' => 'Added notes',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.notes', 'Added notes');
    }

    public function test_cannot_update_reversed_debit(): void
    {
        $debit = $this->createDebit([
            'amount' => 100,
            'status' => DebitStatus::Reversed->value,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/debits/{$debit->id}", [
                'description' => 'Should fail',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_update_fully_allocated_debit(): void
    {
        $debit = $this->createDebit([
            'amount' => 100,
            'status' => DebitStatus::FullyAllocated->value,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/debits/{$debit->id}", [
                'description' => 'Should fail',
            ]);

        $response->assertStatus(422);
    }

    // ─── Delete Debit ───────────────────────────────────────────

    public function test_can_delete_debit(): void
    {
        $debit = $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/debits/{$debit->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('debits', ['id' => $debit->id]);
    }

    public function test_cannot_delete_debit_with_allocations(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order = $this->createOrder();

        DebitAllocation::create([
            'debit_id' => $debit->id,
            'order_id' => $order->id,
            'amount' => 50,
            'allocated_by' => $this->user->id,
            'allocated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/debits/{$debit->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('debits', ['id' => $debit->id]);
    }

    // ─── Allocate Debit ─────────────────────────────────────────

    public function test_can_allocate_debit(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order->id,
                'amount' => 60,
                'notes' => 'Partial allocation',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.notes', 'Partial allocation');

        $this->assertEquals(60.0, (float) $response->json('data.amount'));

        $this->assertDatabaseHas('debit_allocations', [
            'debit_id' => $debit->id,
            'order_id' => $order->id,
            'amount' => '60.00',
        ]);

        // Verify status updated to partially_allocated
        $this->assertDatabaseHas('debits', [
            'id' => $debit->id,
            'status' => 'partially_allocated',
        ]);
    }

    public function test_can_fully_allocate_debit(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order->id,
                'amount' => 100,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('debits', [
            'id' => $debit->id,
            'status' => 'fully_allocated',
        ]);
    }

    public function test_cannot_allocate_more_than_remaining(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        // Allocate 80
        $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order1->id,
                'amount' => 80,
            ]);

        // Try to allocate 30 (only 20 remaining)
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order2->id,
                'amount' => 30,
            ]);

        $response->assertStatus(422);
    }

    public function test_multiple_allocations(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order1->id,
                'amount' => 40,
            ])->assertOk();

        $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order2->id,
                'amount' => 60,
            ])->assertOk();

        // Should be fully allocated now
        $this->assertDatabaseHas('debits', [
            'id' => $debit->id,
            'status' => 'fully_allocated',
        ]);

        $this->assertEquals(2, DebitAllocation::where('debit_id', $debit->id)->count());
    }

    public function test_allocate_requires_order_id(): void
    {
        $debit = $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'amount' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_allocate_requires_amount(): void
    {
        $debit = $this->createDebit(['amount' => 100]);
        $order = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/allocate", [
                'order_id' => $order->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ─── List Allocations ───────────────────────────────────────

    public function test_can_list_allocations(): void
    {
        $debit = $this->createDebit(['amount' => 200]);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        DebitAllocation::create([
            'debit_id' => $debit->id,
            'order_id' => $order1->id,
            'amount' => 80,
            'allocated_by' => $this->user->id,
            'allocated_at' => now(),
        ]);
        DebitAllocation::create([
            'debit_id' => $debit->id,
            'order_id' => $order2->id,
            'amount' => 50,
            'allocated_by' => $this->user->id,
            'allocated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/{$debit->id}/allocations");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // ─── Reverse Debit ──────────────────────────────────────────

    public function test_can_reverse_debit(): void
    {
        $debit = $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/reverse", [
                'reason' => 'Customer requested cancellation',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'reversed');

        $this->assertDatabaseHas('debits', [
            'id' => $debit->id,
            'status' => 'reversed',
        ]);
    }

    public function test_cannot_reverse_already_reversed_debit(): void
    {
        $debit = $this->createDebit([
            'amount' => 100,
            'status' => DebitStatus::Reversed->value,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/debits/{$debit->id}/reverse", [
                'reason' => 'Try again',
            ]);

        $response->assertStatus(422);
    }

    // ─── Customer Balance ───────────────────────────────────────

    public function test_can_get_customer_balance(): void
    {
        $this->createDebit(['amount' => 200]);
        $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/customer/{$this->customer->id}/balance");

        $response->assertOk()
            ->assertJsonPath('data.customer_id', $this->customer->id);

        $this->assertEquals(300.0, (float) $response->json('data.debit_balance'));
    }

    public function test_customer_balance_excludes_reversed(): void
    {
        $this->createDebit(['amount' => 200]);
        $this->createDebit(['amount' => 100, 'status' => DebitStatus::Reversed->value]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/customer/{$this->customer->id}/balance");

        $response->assertOk();

        $this->assertEquals(200.0, (float) $response->json('data.debit_balance'));
    }

    public function test_customer_balance_accounts_for_allocations(): void
    {
        $debit = $this->createDebit(['amount' => 200]);
        $order = $this->createOrder();

        DebitAllocation::create([
            'debit_id' => $debit->id,
            'order_id' => $order->id,
            'amount' => 75,
            'allocated_by' => $this->user->id,
            'allocated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/customer/{$this->customer->id}/balance");

        $response->assertOk();

        $this->assertEquals(125.0, (float) $response->json('data.debit_balance'));
    }

    // ─── Customer Debits ────────────────────────────────────────

    public function test_can_get_customer_debits(): void
    {
        $this->createDebit(['amount' => 200]);
        $this->createDebit(['amount' => 100]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/debits/customer/{$this->customer->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // ─── Summary ────────────────────────────────────────────────

    public function test_can_get_summary(): void
    {
        $this->createDebit(['amount' => 100, 'status' => DebitStatus::Pending->value]);
        $this->createDebit(['amount' => 200, 'status' => DebitStatus::Pending->value]);
        $this->createDebit(['amount' => 150, 'status' => DebitStatus::PartiallyAllocated->value]);
        $this->createDebit(['amount' => 300, 'status' => DebitStatus::FullyAllocated->value]);
        $this->createDebit(['amount' => 50, 'status' => DebitStatus::Reversed->value]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/debits/summary');

        $response->assertOk()
            ->assertJsonPath('data.pending_count', 2)
            ->assertJsonPath('data.partially_allocated_count', 1)
            ->assertJsonPath('data.fully_allocated_count', 1)
            ->assertJsonPath('data.reversed_count', 1);

        $this->assertEquals(300.0, (float) $response->json('data.pending_amount'));
    }

    // ─── Auth ───────────────────────────────────────────────────

    public function test_requires_auth_for_list(): void
    {
        $this->getJson('/api/v2/debits')->assertStatus(401);
    }

    public function test_requires_auth_for_create(): void
    {
        $this->postJson('/api/v2/debits', [])->assertStatus(401);
    }

    public function test_requires_auth_for_show(): void
    {
        $this->getJson('/api/v2/debits/some-id')->assertStatus(401);
    }

    public function test_requires_auth_for_allocate(): void
    {
        $this->postJson('/api/v2/debits/some-id/allocate', [])->assertStatus(401);
    }

    public function test_requires_auth_for_reverse(): void
    {
        $this->postJson('/api/v2/debits/some-id/reverse', [])->assertStatus(401);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function createDebit(array $overrides = []): Debit
    {
        return Debit::create(array_merge([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'debit_type' => 'customer_credit',
            'source' => 'manual',
            'amount' => 100.00,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ], $overrides));
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-' . uniqid(),
            'source' => 'pos',
            'status' => 'completed',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'total' => 115.00,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
