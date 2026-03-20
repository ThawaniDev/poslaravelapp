<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BakeryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;
    private string $otherStoreId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        // Primary store
        $org = Organization::create(['name' => 'Bakery Org', 'slug' => 'bakery-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Bakery Store', 'slug' => 'bakery-store-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Baker', 'email' => 'baker-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        // Other store for cross-store scoping tests
        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Store', 'slug' => 'other-store-' . uniqid(), 'is_active' => true]);
        $this->otherStoreId = $otherStore->id;
        $otherUser = User::create(['name' => 'Other Baker', 'email' => 'other-baker-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS bakery_recipes');
        DB::statement('CREATE TABLE bakery_recipes (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, product_id VARCHAR(36), name VARCHAR(255) NOT NULL, expected_yield INTEGER, prep_time_minutes INTEGER, bake_time_minutes INTEGER, bake_temperature_c INTEGER, instructions TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS production_schedules');
        DB::statement('CREATE TABLE production_schedules (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, recipe_id VARCHAR(36) NOT NULL, schedule_date DATE NOT NULL, planned_batches INTEGER NOT NULL, actual_batches INTEGER, planned_yield INTEGER, actual_yield INTEGER, status VARCHAR(20) NOT NULL DEFAULT "planned", notes TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS custom_cake_orders');
        DB::statement('CREATE TABLE custom_cake_orders (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, customer_id VARCHAR(36) NOT NULL, order_id VARCHAR(36), description TEXT NOT NULL, size VARCHAR(50) NOT NULL, flavor VARCHAR(100) NOT NULL, decoration_notes TEXT, delivery_date DATE NOT NULL, delivery_time VARCHAR(10), price DECIMAL(10,2) NOT NULL, deposit_paid DECIMAL(10,2), status VARCHAR(20) NOT NULL DEFAULT "ordered", reference_image_url VARCHAR(500), created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v2/industry/bakery/recipes')->assertUnauthorized();
    }

    // ── RECIPES: CRUD ───────────────────────────────────

    public function test_list_recipes_returns_paginated(): void
    {
        $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Recipe 1'], $this->h());
        $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Recipe 2'], $this->h());

        $res = $this->getJson('/api/v2/industry/bakery/recipes', $this->h());
        $res->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_list_recipes_filters_by_search(): void
    {
        $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Sourdough Bread'], $this->h());
        $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Croissant'], $this->h());

        $res = $this->getJson('/api/v2/industry/bakery/recipes?search=sourdough', $this->h());
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_create_recipe_with_all_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/recipes', [
            'name' => 'Sourdough Bread',
            'product_id' => 'prod-1',
            'expected_yield' => 10,
            'prep_time_minutes' => 30,
            'bake_time_minutes' => 45,
            'bake_temperature_c' => 230,
            'instructions' => 'Mix, proof 4h, bake.',
        ], $this->h());

        $res->assertCreated()
            ->assertJsonPath('data.name', 'Sourdough Bread')
            ->assertJsonPath('data.expected_yield', 10);
    }

    public function test_create_recipe_requires_name(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/recipes', [], $this->h());
        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_update_recipe(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Original'], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/recipes/{$id}", ['name' => 'Updated', 'bake_time_minutes' => 60], $this->h());
        $res->assertOk()->assertJsonPath('data.name', 'Updated');
    }

    public function test_delete_recipe(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'To Delete'], $this->h());
        $id = $create->json('data.id');

        $this->deleteJson("/api/v2/industry/bakery/recipes/{$id}", [], $this->h())->assertOk();
        $this->assertDatabaseMissing('bakery_recipes', ['id' => $id]);
    }

    public function test_cannot_update_recipe_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'My Recipe'], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/recipes/{$id}", ['name' => 'Hijacked'], $this->h($this->otherToken));
        $res->assertNotFound();
    }

    public function test_cannot_delete_recipe_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'My Recipe'], $this->h());
        $id = $create->json('data.id');

        $this->deleteJson("/api/v2/industry/bakery/recipes/{$id}", [], $this->h($this->otherToken))->assertNotFound();
    }

    // ── PRODUCTION SCHEDULES ────────────────────────────

    private function createRecipe(): string
    {
        return $this->postJson('/api/v2/industry/bakery/recipes', ['name' => 'Test Recipe'], $this->h())->json('data.id');
    }

    public function test_create_production_schedule(): void
    {
        $recipeId = $this->createRecipe();

        $res = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId,
            'schedule_date' => '2025-06-15',
            'planned_batches' => 5,
            'planned_yield' => 50,
        ], $this->h());

        $res->assertCreated()->assertJsonPath('data.planned_batches', 5);
    }

    public function test_create_production_schedule_requires_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/production-schedules', [], $this->h());
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['recipe_id', 'schedule_date', 'planned_batches']);
    }

    public function test_update_production_schedule(): void
    {
        $recipeId = $this->createRecipe();
        $create = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId, 'schedule_date' => '2025-06-20', 'planned_batches' => 3,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/production-schedules/{$id}", [
            'actual_batches' => 3, 'actual_yield' => 28,
        ], $this->h());
        $res->assertOk()->assertJsonPath('data.actual_yield', 28);
    }

    public function test_update_production_schedule_status(): void
    {
        $recipeId = $this->createRecipe();
        $create = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId, 'schedule_date' => '2025-06-21', 'planned_batches' => 4,
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/bakery/production-schedules/{$id}/status", ['status' => 'in_progress'], $this->h())->assertOk();
    }

    public function test_production_schedule_status_must_be_valid(): void
    {
        $recipeId = $this->createRecipe();
        $create = $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId, 'schedule_date' => '2025-06-22', 'planned_batches' => 2,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/bakery/production-schedules/{$id}/status", ['status' => 'invalid'], $this->h());
        $res->assertUnprocessable();
    }

    public function test_list_production_schedules_filters_by_date(): void
    {
        $recipeId = $this->createRecipe();
        $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId, 'schedule_date' => '2025-06-10', 'planned_batches' => 2,
        ], $this->h());
        $this->postJson('/api/v2/industry/bakery/production-schedules', [
            'recipe_id' => $recipeId, 'schedule_date' => '2025-06-15', 'planned_batches' => 3,
        ], $this->h());

        $res = $this->getJson('/api/v2/industry/bakery/production-schedules?schedule_date=2025-06-10', $this->h());
        $res->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── CUSTOM CAKE ORDERS ──────────────────────────────

    public function test_create_custom_cake_order(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => 'cust-1',
            'description' => 'Two-tier chocolate cake',
            'size' => '10 inch',
            'flavor' => 'Chocolate',
            'delivery_date' => '2025-07-01',
            'price' => 120.00,
            'deposit_paid' => 50.00,
        ], $this->h());

        $res->assertCreated()->assertJsonPath('data.flavor', 'Chocolate');
    }

    public function test_create_cake_order_requires_fields(): void
    {
        $res = $this->postJson('/api/v2/industry/bakery/cake-orders', [], $this->h());
        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'description', 'size', 'flavor', 'delivery_date', 'price']);
    }

    public function test_update_cake_order_status(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => 'cust-2', 'description' => 'Red velvet', 'size' => '12 inch',
            'flavor' => 'Red Velvet', 'delivery_date' => '2025-07-05', 'price' => 150.00,
        ], $this->h());
        $id = $create->json('data.id');

        $this->patchJson("/api/v2/industry/bakery/cake-orders/{$id}/status", ['status' => 'in_production'], $this->h())->assertOk();
    }

    public function test_cake_order_status_must_be_valid(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => 'cust-3', 'description' => 'Vanilla', 'size' => '8 inch',
            'flavor' => 'Vanilla', 'delivery_date' => '2025-07-10', 'price' => 80.00,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->patchJson("/api/v2/industry/bakery/cake-orders/{$id}/status", ['status' => 'invalid'], $this->h());
        $res->assertUnprocessable();
    }

    public function test_cannot_update_cake_order_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/bakery/cake-orders', [
            'customer_id' => 'cust-4', 'description' => 'Mine', 'size' => '6 inch',
            'flavor' => 'Lemon', 'delivery_date' => '2025-07-15', 'price' => 60.00,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/bakery/cake-orders/{$id}", ['deposit_paid' => 30.00], $this->h($this->otherToken));
        $res->assertNotFound();
    }
}
