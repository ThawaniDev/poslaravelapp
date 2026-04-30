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

class PredefinedCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private BusinessType $businessType;

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
            'email' => 'test@predefined.com',
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
    }

    // ─── List Categories ──────────────────────────────────────

    public function test_can_list_predefined_categories(): void
    {
        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'name_ar' => 'ألبان',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Beverages',
            'name_ar' => 'مشروبات',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_categories_filters_by_business_type(): void
    {
        $pharmacyType = BusinessType::create([
            'name' => 'Pharmacy',
            'name_ar' => 'صيدلية',
            'slug' => 'pharmacy',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $pharmacyType->id,
            'name' => 'Vitamins',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories?business_type_id=' . $this->businessType->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Dairy');
    }

    public function test_list_categories_filters_by_active_status(): void
    {
        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Active Category',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories?is_active=1');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Active Category');
    }

    public function test_list_categories_search(): void
    {
        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Frozen Foods',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Fresh Produce',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories?search=frozen');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_categories_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/predefined-catalog/categories');

        $response->assertUnauthorized();
    }

    // ─── Category Tree ────────────────────────────────────────

    public function test_can_get_category_tree(): void
    {
        $parent = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'parent_id' => $parent->id,
            'name' => 'Milk',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'parent_id' => $parent->id,
            'name' => 'Cheese',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories/tree?business_type_id=' . $this->businessType->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data); // Only parent at root
        $this->assertCount(2, $data[0]['children']); // Two children
    }

    // ─── Show Category ────────────────────────────────────────

    public function test_can_show_predefined_category(): void
    {
        $category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'name_ar' => 'ألبان',
            'description' => 'Dairy products',
            'image_url' => 'https://example.com/dairy.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories/' . $category->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Dairy')
            ->assertJsonPath('data.name_ar', 'ألبان')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_show_nonexistent_category_returns_404(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories/00000000-0000-0000-0000-000000000099');

        $response->assertNotFound();
    }

    // ─── Create Category ──────────────────────────────────────

    public function test_can_create_predefined_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/categories', [
                'business_type_id' => $this->businessType->id,
                'name' => 'Canned Goods',
                'name_ar' => 'معلبات',
                'description' => 'Canned food items',
                'sort_order' => 5,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Canned Goods')
            ->assertJsonPath('data.name_ar', 'معلبات');

        $this->assertDatabaseHas('predefined_categories', [
            'name' => 'Canned Goods',
            'business_type_id' => $this->businessType->id,
        ]);
    }

    public function test_can_create_child_category(): void
    {
        $parent = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/categories', [
                'business_type_id' => $this->businessType->id,
                'parent_id' => $parent->id,
                'name' => 'Yogurt',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_create_category_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/categories', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_type_id', 'name']);
    }

    // ─── Update Category ──────────────────────────────────────

    public function test_can_update_predefined_category(): void
    {
        $category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Dairy',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/predefined-catalog/categories/' . $category->id, [
                'name' => 'Dairy Products',
                'name_ar' => 'منتجات الألبان',
                'sort_order' => 10,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Dairy Products');

        $this->assertDatabaseHas('predefined_categories', [
            'id' => $category->id,
            'name' => 'Dairy Products',
        ]);
    }

    // ─── Delete Category ──────────────────────────────────────

    public function test_can_delete_empty_predefined_category(): void
    {
        $category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Empty Category',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/predefined-catalog/categories/' . $category->id);

        $response->assertOk();
        $this->assertDatabaseMissing('predefined_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Category With Products',
            'is_active' => true,
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $category->id,
            'name' => 'Test Product',
            'sell_price' => 5.00,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/predefined-catalog/categories/' . $category->id);

        $response->assertStatus(422);
        $this->assertDatabaseHas('predefined_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $parent = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Parent',
            'is_active' => true,
        ]);

        PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/predefined-catalog/categories/' . $parent->id);

        $response->assertStatus(422);
        $this->assertDatabaseHas('predefined_categories', ['id' => $parent->id]);
    }

    // ─── Clone Category ───────────────────────────────────────

    public function test_can_clone_category_to_store(): void
    {
        $category = PredefinedCategory::create([
            'business_type_id' => $this->businessType->id,
            'name' => 'Snacks',
            'name_ar' => 'وجبات خفيفة',
            'is_active' => true,
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $category->id,
            'name' => 'Chips',
            'name_ar' => 'رقائق',
            'sell_price' => 1.50,
            'unit' => 'piece',
        ]);

        PredefinedProduct::create([
            'business_type_id' => $this->businessType->id,
            'predefined_category_id' => $category->id,
            'name' => 'Cookies',
            'sell_price' => 2.00,
            'unit' => 'piece',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/predefined-catalog/categories/' . $category->id . '/clone');

        $response->assertCreated()
            ->assertJsonPath('success', true);

        // Verify the cloned category exists in the user's catalog
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->org->id,
            'name' => 'Snacks',
        ]);

        // Verify the cloned products exist
        $this->assertDatabaseHas('products', [
            'organization_id' => $this->org->id,
            'name' => 'Chips',
        ]);
    }

    // ─── Pagination ───────────────────────────────────────────

    public function test_categories_are_paginated(): void
    {
        for ($i = 0; $i < 30; $i++) {
            PredefinedCategory::create([
                'business_type_id' => $this->businessType->id,
                'name' => "Category $i",
                'is_active' => true,
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/predefined-catalog/categories?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonPath('data.total', 30)
            ->assertJsonPath('data.current_page', 1);

        $data = $response->json('data.data');
        $this->assertCount(10, $data);
    }
}
