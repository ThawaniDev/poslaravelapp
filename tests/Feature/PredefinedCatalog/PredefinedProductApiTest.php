<?php

namespace Tests\Feature\PredefinedCatalog;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use App\Domain\PredefinedCatalog\Models\PredefinedProduct;
use App\Domain\PredefinedCatalog\Models\PredefinedProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredefinedProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private BusinessType $businessType;
    private PredefinedCategory $category;

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
            'email' => 'test@predefined-product.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->businessType = BusinessType::create([
            'name' => 'Grocery',
            'name_ar' => 'بقالة',
            'slug' => 'grocery',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'name_ar' => 'ألبان',
            'is_active' => true,
        ]);
    }

    // ─── List Products ────────────────────────────────────────

    public function test_can_list_predefined_products(): void
    {
        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Whole Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Cheddar Cheese',
            'sell_price' => 3.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_products_filters_by_category(): void
    {
        $otherCategory = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Bakery',
            'is_active' => true,
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $otherCategory->id,
            'name' => 'Bread',
            'sell_price' => 0.80,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products?predefined_category_id=' . $this->category->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Milk');
    }

    public function test_list_products_filters_by_business_type(): void
    {
        $pharmacyType = BusinessType::create([
            'name' => 'Pharmacy',
            'name_ar' => 'صيدلية',
            'slug' => 'pharmacy',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $pharmacyCategory = PredefinedCategory::create([
            'business_type_id' => $pharmacyType->id,
            'name' => 'Vitamins',
            'is_active' => true,
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $pharmacyType->id,
            'predefined_category_id' => $pharmacyCategory->id,
            'name' => 'Vitamin C',
            'sell_price' => 5.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products?business_type_id=' . $this->businessType->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Milk');
    }

    public function test_list_products_search_by_name(): void
    {
        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Whole Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Cheddar Cheese',
            'sell_price' => 3.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products?search=milk');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Whole Milk');
    }

    public function test_list_products_search_by_sku(): void
    {
        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sku' => 'MILK-001',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Cheese',
            'sku' => 'CHEESE-001',
            'sell_price' => 3.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products?search=MILK-001');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_products_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/predefined-catalog/products');

        $response->assertUnauthorized();
    }

    // ─── Show Product ─────────────────────────────────────────

    public function test_can_show_predefined_product(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Whole Milk',
            'name_ar' => 'حليب كامل',
            'sku' => 'MILK-001',
            'barcode' => '1234567890123',
            'sell_price' => 1.500,
            'cost_price' => 0.800,
            'unit' => 'piece',
            'tax_rate' => 5.00,
            'is_weighable' => false,
            'is_active' => true,
            'age_restricted' => false,
        ]);

        PredefinedProductImage::create([
            'predefined_product_id' => $product->id,
            'image_url' => 'https://example.com/milk.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Whole Milk')
            ->assertJsonPath('data.name_ar', 'حليب كامل')
            ->assertJsonPath('data.sku', 'MILK-001')
            ->assertJsonPath('data.barcode', '1234567890123');
    }

    public function test_show_product_includes_category(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('data.category.name', 'Dairy');
    }

    public function test_show_nonexistent_product_returns_404(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products/nonexistent-id');

        $response->assertNotFound();
    }

    // ─── Create Product ───────────────────────────────────────

    public function test_can_create_predefined_product(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', [
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => 'Greek Yogurt',
                'name_ar' => 'زبادي يوناني',
                'sku' => 'YOG-001',
                'sell_price' => 2.500,
                'cost_price' => 1.200,
                'unit' => 'piece',
                'tax_rate' => 5.00,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Greek Yogurt')
            ->assertJsonPath('data.sku', 'YOG-001');

        $this->assertDatabaseHas('predefined_products', [
            'name' => 'Greek Yogurt',
            'sku' => 'YOG-001',
        ]);
    }

    public function test_can_create_product_with_minimum_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', [
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => 'Simple Product',
                'sell_price' => 1.00,
                'unit' => 'piece',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Simple Product');
    }

    public function test_can_create_weighable_product(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', [
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => 'Bananas',
                'sell_price' => 1.200,
                'unit' => 'kg',
                'is_weighable' => true,
                'tare_weight' => 0.05,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.unit', 'kg')
            ->assertJsonPath('data.is_weighable', true);
    }

    public function test_create_product_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_type_id', 'name', 'sell_price']);
    }

    public function test_create_product_validates_unit_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', [
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => 'Test',
                'sell_price' => 1.00,
                'unit' => 'invalid-unit',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['unit']);
    }

    public function test_create_product_validates_price_is_numeric(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products', [
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => 'Test',
                'sell_price' => 'not-a-number',
                'unit' => 'piece',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sell_price']);
    }

    // ─── Update Product ───────────────────────────────────────

    public function test_can_update_predefined_product(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/predefined-catalog/products/' . $product->id, [
                'name' => 'Whole Milk 1L',
                'sell_price' => 1.750,
                'cost_price' => 0.900,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Whole Milk 1L');

        $this->assertDatabaseHas('predefined_products', [
            'id' => $product->id,
            'name' => 'Whole Milk 1L',
        ]);
    }

    public function test_can_update_product_category(): void
    {
        $newCategory = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Beverages',
            'is_active' => true,
        ]);

        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Chocolate Milk',
            'sell_price' => 2.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/predefined-catalog/products/' . $product->id, [
                'predefined_category_id' => $newCategory->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('predefined_products', [
            'id' => $product->id,
            'predefined_category_id' => $newCategory->id,
        ]);
    }

    // ─── Delete Product ───────────────────────────────────────

    public function test_can_delete_predefined_product(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Expired Product',
            'sell_price' => 1.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/predefined-catalog/products/' . $product->id);

        $response->assertOk();
        $this->assertDatabaseMissing('predefined_products', ['id' => $product->id]);
    }

    public function test_delete_product_cascades_images(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product With Images',
            'sell_price' => 1.00,
            'unit' => 'piece',
        ]);

        $image = PredefinedProductImage::create([
            'predefined_product_id' => $product->id,
            'image_url' => 'https://example.com/img.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/predefined-catalog/products/' . $product->id);

        $response->assertOk();
        $this->assertDatabaseMissing('predefined_product_images', ['id' => $image->id]);
    }

    // ─── Clone Product ────────────────────────────────────────

    public function test_can_clone_product_to_store(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Yogurt',
            'name_ar' => 'زبادي',
            'sku' => 'YOG-001',
            'sell_price' => 2.500,
            'cost_price' => 1.200,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products/' . $product->id . '/clone');

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('products', [
            'organization_id' => $this->org->id,
            'name' => 'Yogurt',
        ]);
    }

    // ─── Bulk Actions ─────────────────────────────────────────

    public function test_can_bulk_delete_products(): void
    {
        $p1 = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product 1',
            'sell_price' => 1.00,
            'unit' => 'piece',
        ]);

        $p2 = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product 2',
            'sell_price' => 2.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products/bulk-action', [
                'action' => 'delete',
                'product_ids' => [$p1->id, $p2->id],
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('predefined_products', ['id' => $p1->id]);
        $this->assertDatabaseMissing('predefined_products', ['id' => $p2->id]);
    }

    public function test_can_bulk_activate_products(): void
    {
        $p1 = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product 1',
            'sell_price' => 1.00,
            'unit' => 'piece',
            'is_active' => false,
        ]);

        $p2 = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product 2',
            'sell_price' => 2.00,
            'unit' => 'piece',
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products/bulk-action', [
                'action' => 'activate',
                'product_ids' => [$p1->id, $p2->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('predefined_products', [
            'id' => $p1->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('predefined_products', [
            'id' => $p2->id,
            'is_active' => true,
        ]);
    }

    public function test_can_bulk_deactivate_products(): void
    {
        $p1 = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product 1',
            'sell_price' => 1.00,
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/products/bulk-action', [
                'action' => 'deactivate',
                'product_ids' => [$p1->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('predefined_products', [
            'id' => $p1->id,
            'is_active' => false,
        ]);
    }

    // ─── Clone All ────────────────────────────────────────────

    public function test_can_clone_all_for_business_type(): void
    {
        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Milk',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Cheese',
            'sell_price' => 3.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/clone-all', [
                'business_type_id' => $this->businessType->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        // Verify all products were cloned
        $this->assertDatabaseHas('products', [
            'organization_id' => $this->org->id,
            'name' => 'Milk',
        ]);

        $this->assertDatabaseHas('products', [
            'organization_id' => $this->org->id,
            'name' => 'Cheese',
        ]);

        // Verify the category was cloned
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->org->id,
            'name' => 'Dairy',
        ]);
    }

    // ─── Pagination ───────────────────────────────────────────

    public function test_products_are_paginated(): void
    {
        for ($i = 0; $i < 25; $i++) {
            PredefinedProduct::create([
                'business_type_id' => $this->businessType->id,
                'predefined_category_id' => $this->category->id,
                'name' => "Product $i",
                'sell_price' => 1.00 + $i,
                'unit' => 'piece',
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonPath('data.total', 25)
            ->assertJsonPath('data.current_page', 2);

        $data = $response->json('data.data');
        $this->assertCount(10, $data);
    }

    // ─── Product Images ───────────────────────────────────────

    public function test_show_product_includes_images(): void
    {
        $product = PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $this->category->id,
            'name' => 'Product With Images',
            'sell_price' => 5.00,
            'unit' => 'piece',
        ]);

        PredefinedProductImage::create([
            'predefined_product_id' => $product->id,
            'image_url' => 'https://example.com/img1.jpg',
            'sort_order' => 0,
        ]);

        PredefinedProductImage::create([
            'predefined_product_id' => $product->id,
            'image_url' => 'https://example.com/img2.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/products/' . $product->id);

        $response->assertOk();

        $images = $response->json('data.images');
        $this->assertCount(2, $images);
        $this->assertEquals('https://example.com/img1.jpg', $images[0]['image_url']);
    }
}
