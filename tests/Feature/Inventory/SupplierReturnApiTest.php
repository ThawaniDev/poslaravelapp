<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\SupplierReturnStatus;
use App\Domain\Inventory\Models\SupplierReturn;
use App\Domain\Inventory\Models\SupplierReturnItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReturnApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;
    private Product $product2;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
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
            'email' => 'test@returns.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Defective Widget',
            'sell_price' => 25.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Expired Goods',
            'sell_price' => 15.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Supplies',
            'phone' => '+966500000000',
            'email' => 'acme@example.com',
            'is_active' => true,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────

    public function test_can_create_supplier_return_draft(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'SR-001',
                'reason' => 'Defective items',
                'notes' => 'Batch had quality issues',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                        'unit_cost' => 5.00,
                        'reason' => 'Broken packaging',
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'quantity' => 5,
                        'unit_cost' => 3.00,
                        'reason' => 'Expired',
                        'batch_number' => 'B-2025-01',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reference_number', 'SR-001')
            ->assertJsonPath('data.reason', 'Defective items');

        $this->assertEquals(65.0, (float) $response->json('data.total_amount'));
        $this->assertDatabaseCount('supplier_return_items', 2);
    }

    public function test_create_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_requires_supplier(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 5.00],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');
    }

    // ─── Show ─────────────────────────────────────────────────

    public function test_can_show_supplier_return_with_items(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/supplier-returns/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.supplier.name', 'Acme Supplies');
    }

    // ─── List ─────────────────────────────────────────────────

    public function test_can_list_supplier_returns(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product2->id, 'quantity' => 3, 'unit_cost' => 4.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/supplier-returns');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_can_filter_returns_by_status(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/supplier-returns?status=draft');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);

        $response2 = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/supplier-returns?status=completed');

        $response2->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_can_search_returns(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'SR-SEARCH-001',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/supplier-returns?search=SR-SEARCH');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ─── Update ───────────────────────────────────────────────

    public function test_can_update_draft_return(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/inventory/supplier-returns/{$id}", [
                'reason' => 'Updated reason',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 20, 'unit_cost' => 3.00],
                    ['product_id' => $this->product2->id, 'quantity' => 10, 'unit_cost' => 1.50],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reason', 'Updated reason');

        $this->assertEquals(75.0, (float) $response->json('data.total_amount'));
    }

    // ─── Status Workflow ──────────────────────────────────────

    public function test_full_workflow_draft_submit_approve_complete(): void
    {
        // Create draft
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reason' => 'Defective batch',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5.00],
                ],
            ]);

        $id = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // Submit
        $submitResponse = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");

        $submitResponse->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        // Approve
        $approveResponse = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $this->user->id);

        $this->assertNotNull($approveResponse->json('data.approved_at'));

        // Complete
        $completeResponse = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/complete");

        $completeResponse->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($completeResponse->json('data.completed_at'));
    }

    public function test_cannot_submit_non_draft(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        // Submit it first
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");

        // Try to submit again
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");

        $response->assertStatus(422);
    }

    public function test_cannot_approve_non_submitted(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/approve");

        $response->assertStatus(422);
    }

    public function test_cannot_complete_non_approved(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/complete");

        $response->assertStatus(422);
    }

    // ─── Cancel ───────────────────────────────────────────────

    public function test_can_cancel_draft(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_can_cancel_submitted(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_completed(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/approve");
        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/complete");

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/cancel");

        $response->assertStatus(422);
    }

    // ─── Delete ───────────────────────────────────────────────

    public function test_can_delete_draft_return(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/inventory/supplier-returns/{$id}");

        $response->assertOk();
        $this->assertDatabaseMissing('supplier_returns', ['id' => $id]);
        $this->assertDatabaseCount('supplier_return_items', 0);
    }

    public function test_cannot_delete_non_draft_return(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $id = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/v2/inventory/supplier-returns/{$id}/submit");

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/inventory/supplier-returns/{$id}");

        $response->assertStatus(422);
    }

    // ─── Enhanced Supplier Fields ─────────────────────────────

    public function test_can_create_supplier_with_enhanced_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/suppliers', [
                'name' => 'Advanced Supplier',
                'phone' => '+966512345678',
                'email' => 'advanced@supplier.com',
                'website' => 'https://supplier.com',
                'address' => '123 Main St',
                'city' => 'Riyadh',
                'country' => 'Saudi Arabia',
                'postal_code' => '12345',
                'contact_person' => 'Ahmed',
                'tax_number' => 'TAX-001',
                'payment_terms' => 'Net 30',
                'bank_name' => 'Al Rajhi Bank',
                'bank_account' => '1234567890',
                'iban' => 'SA0380000000608010167519',
                'credit_limit' => 50000.00,
                'rating' => 4,
                'category' => 'Food & Beverage',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.website', 'https://supplier.com')
            ->assertJsonPath('data.city', 'Riyadh')
            ->assertJsonPath('data.country', 'Saudi Arabia')
            ->assertJsonPath('data.bank_name', 'Al Rajhi Bank')
            ->assertJsonPath('data.iban', 'SA0380000000608010167519')
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.category', 'Food & Beverage');

        $this->assertEquals(50000.0, (float) $response->json('data.credit_limit'));
    }

    public function test_can_update_supplier_enhanced_fields(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/suppliers/{$this->supplier->id}", [
                'website' => 'https://acme.com',
                'bank_name' => 'Saudi National Bank',
                'rating' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.website', 'https://acme.com')
            ->assertJsonPath('data.bank_name', 'Saudi National Bank')
            ->assertJsonPath('data.rating', 5);
    }

    public function test_supplier_show_includes_counts(): void
    {
        // Create a supplier return to have a count
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/catalog/suppliers/{$this->supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Acme Supplies');
    }

    // ─── Filter by Supplier ───────────────────────────────────

    public function test_can_filter_returns_by_supplier(): void
    {
        $supplier2 = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Supplier',
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 2.00],
                ],
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/supplier-returns', [
                'store_id' => $this->store->id,
                'supplier_id' => $supplier2->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 1.00],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/supplier-returns?supplier_id={$this->supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }
}
