<?php

namespace Tests\Feature\Catalog;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
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
            'email' => 'test@category.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Tree ─────────────────────────────────────────────────

    public function test_can_get_category_tree(): void
    {
        $parent = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'parent_id' => $parent->id,
            'name' => 'Fruits',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'parent_id' => $parent->id,
            'name' => 'Vegetables',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data); // Only root categories
        $this->assertEquals('Food', $data[0]['name']);
        $this->assertCount(2, $data[0]['children']);
    }

    public function test_tree_filters_inactive_by_default(): void
    {
        Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Active Category',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Category',
            'is_active' => false,
            'sync_version' => 1,
        ]);

        // Default active_only=false means all categories
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories');

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_tree_active_only_filter(): void
    {
        Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Active Category',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Category',
            'is_active' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories?active_only=1');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active Category', $data[0]['name']);
    }

    public function test_tree_scoped_to_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'name' => 'My Category',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Category',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('My Category', $data[0]['name']);
    }

    public function test_tree_requires_auth(): void
    {
        $this->getJson('/api/v2/catalog/categories')
            ->assertUnauthorized();
    }

    // ─── Create Category ──────────────────────────────────────

    public function test_can_create_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/categories', [
                'name' => 'Electronics',
                'name_ar' => 'إلكترونيات',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonPath('data.name_ar', 'إلكترونيات')
            ->assertJsonPath('data.organization_id', $this->org->id)
            ->assertJsonPath('data.sync_version', 1);

        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_can_create_subcategory(): void
    {
        $parent = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/categories', [
                'name' => 'Dairy',
                'parent_id' => $parent->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_create_category_requires_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/categories', [
                'sort_order' => 1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ─── Show Category ────────────────────────────────────────

    public function test_can_show_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Category',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/catalog/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Test Category')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'name_ar', 'parent_id', 'sort_order', 'is_active', 'sync_version'],
            ]);
    }

    public function test_show_nonexistent_category_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->withToken($this->token)
            ->getJson("/api/v2/catalog/categories/{$fakeId}")
            ->assertNotFound();
    }

    // ─── Update Category ──────────────────────────────────────

    public function test_can_update_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/categories/{$category->id}", [
                'name' => 'New Name',
                'sort_order' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.sync_version', 2);
    }

    // ─── Delete Category ──────────────────────────────────────

    public function test_can_delete_empty_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Empty',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Has Products',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Product in Cat',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $parent = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Parent',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        Category::create([
            'organization_id' => $this->org->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/catalog/categories/{$parent->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    // ─── Description Fields ───────────────────────────────────

    public function test_can_create_category_with_description(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/catalog/categories', [
                'name' => 'Described Category',
                'name_ar' => 'تصنيف موصوف',
                'description' => 'This is a test description',
                'description_ar' => 'هذا وصف تجريبي',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', 'This is a test description')
            ->assertJsonPath('data.description_ar', 'هذا وصف تجريبي');
    }

    public function test_can_update_category_description(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Cat',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/catalog/categories/{$category->id}", [
                'description' => 'Updated description',
                'description_ar' => 'وصف محدث',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.description_ar', 'وصف محدث');
    }
}
