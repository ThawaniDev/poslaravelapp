<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;
    private Product $ingredientA;
    private Product $ingredientB;

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
            'email' => 'test@recipe.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Latte',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $this->ingredientA = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee Beans',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        $this->ingredientB = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Milk',
            'sell_price' => 2.00,
            'sync_version' => 1,
        ]);
    }

    // ─── Create Recipe ────────────────────────────────────────

    public function test_can_create_recipe(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Latte Recipe',
                'description' => 'Standard latte preparation.',
                'yield_quantity' => 1,
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02, 'unit' => 'kg', 'waste_percent' => 5],
                    ['ingredient_product_id' => $this->ingredientB->id, 'quantity' => 0.25, 'unit' => 'litre'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Latte Recipe')
            ->assertJsonCount(2, 'data.ingredients');
    }

    public function test_create_recipe_requires_ingredients(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Empty Recipe',
                'ingredients' => [],
            ]);

        $response->assertStatus(422);
    }

    // ─── Show & List ──────────────────────────────────────────

    public function test_can_show_recipe(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Latte Recipe',
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/inventory/recipes/{$create->json('data.id')}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Latte Recipe')
            ->assertJsonCount(1, 'data.ingredients');
    }

    public function test_can_list_recipes(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Recipe A',
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/recipes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── Update Recipe ────────────────────────────────────────

    public function test_can_update_recipe(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Original',
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/inventory/recipes/{$create->json('data.id')}", [
                'name' => 'Updated Recipe',
                'yield_quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Recipe');
        $this->assertEquals(2, $response->json('data.yield_quantity'));
    }

    public function test_can_update_recipe_ingredients(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Recipe',
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/inventory/recipes/{$create->json('data.id')}", [
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.03],
                    ['ingredient_product_id' => $this->ingredientB->id, 'quantity' => 0.30],
                ],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.ingredients');
    }

    // ─── Delete Recipe ────────────────────────────────────────

    public function test_can_delete_recipe(): void
    {
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'To Delete',
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02],
                ],
            ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/inventory/recipes/{$create->json('data.id')}");

        $response->assertOk();
    }

    // ─── Recipe Ingredient Deduction ─────────────────────────

    public function test_recipe_ingredient_deduction_via_service(): void
    {
        // Pre-stock the store with ingredients
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->ingredientA->id,
            'quantity' => 10.00,
            'reserved_quantity' => 0,
            'average_cost' => 8.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->ingredientB->id,
            'quantity' => 50.00,
            'reserved_quantity' => 0,
            'average_cost' => 1.50,
            'sync_version' => 1,
        ]);

        // Create recipe: 1 latte = 0.02 kg beans (5% waste) + 0.25 litre milk
        $create = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/recipes', [
                'product_id' => $this->product->id,
                'name' => 'Latte Recipe',
                'yield_quantity' => 1,
                'ingredients' => [
                    ['ingredient_product_id' => $this->ingredientA->id, 'quantity' => 0.02, 'unit' => 'kg', 'waste_percent' => 5],
                    ['ingredient_product_id' => $this->ingredientB->id, 'quantity' => 0.25, 'unit' => 'litre', 'waste_percent' => 0],
                ],
            ]);

        $recipeId = $create->json('data.id');

        // Deduct for 3 lattes sold
        $recipeService = app(\App\Domain\Inventory\Services\RecipeService::class);
        $recipeService->deductIngredients($recipeId, $this->store->id, 3, $this->user->id);

        // Bean deduction: 3 * 0.02 * 1.05 = 0.063, rounded to 0.06 at decimal(12,2)
        $beanLevel = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->ingredientA->id)
            ->first();
        $this->assertEqualsWithDelta(10.0 - 0.06, (float) $beanLevel->quantity, 0.01);

        // Milk deduction: 3 * 0.25 * 1.0 = 0.75
        $milkLevel = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->ingredientB->id)
            ->first();
        $this->assertEqualsWithDelta(50.0 - 0.75, (float) $milkLevel->quantity, 0.01);
    }
}
