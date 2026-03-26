<?php

namespace Tests\Feature\Catalog;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\InternalBarcodeSequence;
use App\Domain\Catalog\Models\ModifierGroup;
use App\Domain\Catalog\Models\ModifierOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Category $category;

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
            'email' => 'test@catalog.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Beverages',
            'name_ar' => 'مشروبات',
            'is_active' => true,
            'sync_version' => 1,
        ]);
    }

    // ─── List Products ────────────────────────────────────────

    public function test_can_list_products(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Coffee',
            'sell_price' => 2.50,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Tea',
            'sell_price' => 1.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_products_filters_by_category(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Coffee',
            'sell_price' => 2.50,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Bread',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products?category_id=' . $this->category->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Coffee');
    }

    public function test_list_products_search(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Espresso',
            'sku' => 'ESP-001',
            'sell_price' => 3.00,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Water',
            'sell_price' => 0.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products?search=espresso');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_products_filter_by_active(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Active Product',
            'sell_price' => 1.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Product',
            'sell_price' => 1.00,
            'is_active' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products?is_active=1');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Active Product');
    }

    public function test_list_products_requires_auth(): void
    {
        $this->getJson('/api/v2/catalog/products')
            ->assertUnauthorized();
    }

    public function test_list_products_scoped_to_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'My Product',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Product',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'My Product');
    }

    // ─── Create Product ───────────────────────────────────────

    public function test_can_create_product(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Latte',
                'name_ar' => 'لاتيه',
                'category_id' => $this->category->id,
                'sell_price' => 3.50,
                'cost_price' => 1.20,
                'sku' => 'LAT-001',
                'unit' => 'piece',
                'tax_rate' => 15.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Latte')
            ->assertJsonPath('data.name_ar', 'لاتيه')
            ->assertJsonPath('data.sell_price', 3.5)
            ->assertJsonPath('data.sku', 'LAT-001')
            ->assertJsonPath('data.organization_id', $this->org->id);

        $this->assertDatabaseHas('products', [
            'name' => 'Latte',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_create_product_with_barcodes_and_images(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Tea Box',
                'sell_price' => 5.00,
                'barcodes' => [
                    ['barcode' => '1234567890123', 'is_primary' => true],
                    ['barcode' => '9876543210987', 'is_primary' => false],
                ],
                'images' => [
                    ['image_url' => 'https://example.com/tea1.jpg', 'sort_order' => 0],
                    ['image_url' => 'https://example.com/tea2.jpg', 'sort_order' => 1],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tea Box');

        $productId = $response->json('data.id');

        $this->assertDatabaseCount('product_barcodes', 2);
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $productId,
            'barcode' => '1234567890123',
            'is_primary' => true,
        ]);
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_create_product_validation_requires_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'sell_price' => 5.00,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_product_validation_requires_sell_price(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Test Product',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sell_price']);
    }

    public function test_create_product_validation_invalid_unit(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Test',
                'sell_price' => 1.00,
                'unit' => 'gallon',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['unit']);
    }

    public function test_create_product_sets_sync_version(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Sync Test',
                'sell_price' => 1.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.sync_version', 1);
    }

    // ─── Show Product ─────────────────────────────────────────

    public function test_can_show_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Show Me',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/catalog/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonStructure([
                'data' => [
                    'id', 'organization_id', 'category_id', 'name', 'name_ar',
                    'sell_price', 'cost_price', 'sku', 'barcode', 'unit',
                    'tax_rate', 'is_weighable', 'is_active', 'is_combo',
                    'sync_version', 'category', 'barcodes', 'images',
                    'variants', 'modifier_groups', 'store_prices', 'suppliers',
                ],
            ]);
    }

    public function test_show_nonexistent_product_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->withToken($this->token)
            ->getJson("/api/v2/catalog/products/{$fakeId}")
            ->assertNotFound();
    }

    // ─── Update Product ───────────────────────────────────────

    public function test_can_update_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/products/{$product->id}", [
                'name' => 'New Name',
                'sell_price' => 7.50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.sell_price', 7.5)
            ->assertJsonPath('data.sync_version', 2);
    }

    public function test_update_product_replaces_barcodes(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Barcode Product',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => '1111111111111',
            'is_primary' => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/products/{$product->id}", [
                'barcodes' => [
                    ['barcode' => '2222222222222', 'is_primary' => true],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('product_barcodes', ['barcode' => '1111111111111']);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '2222222222222']);
    }

    // ─── Delete Product ───────────────────────────────────────

    public function test_can_delete_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Me',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Soft deleted
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_delete_product_bumps_sync_version(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Version Bump',
            'sell_price' => 1.00,
            'sync_version' => 3,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/products/{$product->id}");

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sync_version' => 4,
        ]);
    }

    // ─── Catalog Endpoint ─────────────────────────────────────

    public function test_catalog_returns_active_products_only(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Active',
            'sell_price' => 1.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive',
            'sell_price' => 1.00,
            'is_active' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/catalog');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active', $data[0]['name']);
    }

    // ─── Changes (Delta Sync) ─────────────────────────────────

    public function test_changes_returns_products_since_version(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Product',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'New Product',
            'sell_price' => 2.00,
            'sync_version' => 5,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/changes?since=3');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('New Product', $data[0]['name']);
    }

    public function test_changes_includes_soft_deleted(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Deleted Item',
            'sell_price' => 1.00,
            'sync_version' => 10,
        ]);

        $product->delete();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/changes?since=5');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['deleted_at']);
    }

    public function test_changes_requires_since_parameter(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/changes')
            ->assertUnprocessable();
    }

    // ─── Barcode Generation ───────────────────────────────────

    public function test_can_generate_barcode(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Barcode Gen',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/catalog/products/{$product->id}/barcode");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $barcode = $response->json('data.barcode');
        $this->assertNotNull($barcode);
        $this->assertStringStartsWith('200', $barcode);
        $this->assertEquals(13, strlen($barcode));

        // Verify barcode record was created
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => $barcode,
        ]);
    }

    public function test_barcode_generation_increments_sequence(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Seq Test',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/catalog/products/{$product->id}/barcode");

        $seq = InternalBarcodeSequence::where('store_id', $this->store->id)->first();
        $this->assertEquals(1, $seq->last_sequence);

        // Generate another
        $product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Seq Test 2',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/catalog/products/{$product2->id}/barcode");

        $seq->refresh();
        $this->assertEquals(2, $seq->last_sequence);
    }

    // ─── Modifiers ────────────────────────────────────────────

    public function test_can_get_modifiers(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Modifier Test',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $group = ModifierGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);

        ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Small',
            'price_adjustment' => 0.00,
        ]);

        ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Large',
            'price_adjustment' => 1.50,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/catalog/products/{$product->id}/modifiers");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Size', $data[0]['name']);
        $this->assertCount(2, $data[0]['options']);
    }

    public function test_can_sync_modifiers(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Sync Modifiers',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/products/{$product->id}/modifiers", [
                'groups' => [
                    [
                        'name' => 'Milk Type',
                        'is_required' => false,
                        'min_select' => 0,
                        'max_select' => 1,
                        'options' => [
                            ['name' => 'Whole Milk', 'price_adjustment' => 0],
                            ['name' => 'Oat Milk', 'price_adjustment' => 0.50],
                        ],
                    ],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseCount('modifier_groups', 1);
        $this->assertDatabaseCount('modifier_options', 2);
        $this->assertDatabaseHas('modifier_groups', ['name' => 'Milk Type']);
    }

    // ─── Pagination ──────────────────────────────────────────

    public function test_list_products_respects_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Product::create([
                'organization_id' => $this->org->id,
                'name' => "Product {$i}",
                'sell_price' => 1.00,
                'sync_version' => 1,
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products?per_page=2');

        $response->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5)
            ->assertJsonCount(2, 'data.data');
    }
}
