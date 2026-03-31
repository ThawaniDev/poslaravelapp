<?php

namespace Tests\Feature\Catalog;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductSupplier;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierApiTest extends TestCase
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
            'email' => 'test@supplier.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── List Suppliers ───────────────────────────────────────

    public function test_can_list_suppliers(): void
    {
        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Supplier A',
        ]);

        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Supplier B',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/suppliers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_suppliers_search(): void
    {
        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Oman Dairy Co',
            'email' => 'dairy@oman.com',
        ]);

        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Gulf Beverages',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/suppliers?search=dairy');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Oman Dairy Co');
    }

    public function test_list_suppliers_scoped_to_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'My Supplier',
        ]);

        Supplier::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Supplier',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/suppliers');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_suppliers_requires_auth(): void
    {
        $this->getJson('/api/v2/catalog/suppliers')
            ->assertUnauthorized();
    }

    // ─── Create Supplier ──────────────────────────────────────

    public function test_can_create_supplier(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/suppliers', [
                'name' => 'Fresh Foods Ltd',
                'phone' => '+96812345678',
                'email' => 'info@freshfoods.com',
                'address' => '123 Market Street, Muscat',
                'notes' => 'Delivers on Tuesdays',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Fresh Foods Ltd')
            ->assertJsonPath('data.phone', '+96812345678')
            ->assertJsonPath('data.email', 'info@freshfoods.com')
            ->assertJsonPath('data.organization_id', $this->org->id);

        $this->assertDatabaseHas('suppliers', ['name' => 'Fresh Foods Ltd']);
    }

    public function test_create_supplier_requires_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/suppliers', [
                'phone' => '+96812345678',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ─── Show Supplier ────────────────────────────────────────

    public function test_can_show_supplier(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Supplier',
            'email' => 'show@test.com',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/catalog/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $supplier->id)
            ->assertJsonPath('data.name', 'Show Supplier')
            ->assertJsonStructure([
                'data' => ['id', 'organization_id', 'name', 'phone', 'email', 'address', 'notes', 'is_active'],
            ]);
    }

    public function test_show_nonexistent_supplier_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->withToken($this->token)
            ->getJson("/api/v2/catalog/suppliers/{$fakeId}")
            ->assertNotFound();
    }

    // ─── Update Supplier ──────────────────────────────────────

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Supplier Name',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/suppliers/{$supplier->id}", [
                'name' => 'Updated Supplier',
                'phone' => '+96899887766',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Supplier')
            ->assertJsonPath('data.phone', '+96899887766');
    }

    // ─── Delete Supplier ──────────────────────────────────────

    public function test_can_delete_supplier_without_products(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Deletable Supplier',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_cannot_delete_supplier_with_linked_products(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Linked Supplier',
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Linked Product',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        ProductSupplier::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'cost_price' => 0.80,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/suppliers/{$supplier->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    // ─── Pagination ──────────────────────────────────────────

    public function test_list_suppliers_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Supplier::create([
                'organization_id' => $this->org->id,
                'name' => "Supplier {$i}",
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/suppliers?per_page=2');

        $response->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5)
            ->assertJsonCount(2, 'data.data');
    }

    // ─── New Fields ───────────────────────────────────────────

    public function test_can_create_supplier_with_new_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/suppliers', [
                'name' => 'Full Supplier',
                'phone' => '96812345678',
                'email' => 'supplier@test.com',
                'contact_person' => 'John Manager',
                'tax_number' => 'TAX-12345',
                'payment_terms' => 'Net 30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.contact_person', 'John Manager')
            ->assertJsonPath('data.tax_number', 'TAX-12345')
            ->assertJsonPath('data.payment_terms', 'Net 30');
    }

    public function test_can_update_supplier_new_fields(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Supplier',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/suppliers/{$supplier->id}", [
                'contact_person' => 'Jane Updated',
                'tax_number' => 'TAX-99999',
                'payment_terms' => 'Net 60',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.contact_person', 'Jane Updated')
            ->assertJsonPath('data.tax_number', 'TAX-99999')
            ->assertJsonPath('data.payment_terms', 'Net 60');
    }
}
