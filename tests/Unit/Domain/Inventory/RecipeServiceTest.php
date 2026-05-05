<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Recipe;
use App\Domain\Inventory\Models\RecipeIngredient;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for RecipeService — Bill of Materials (BOM) engine.
 *
 * Covers:
 *  - Recipe CRUD
 *  - Ingredient deduction with waste_percent
 *  - Proportional scaling (yield_quantity)
 *  - Idempotency key prevents double deduction
 *  - Reversal restores ingredient stock
 *  - Insufficient ingredient stock throws
 *  - findByProductId returns active recipe
 */
class RecipeServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecipeService $service;
    private Organization $org;
    private Store $store;
    private Product $finishedProduct;
    private Product $ingredient1;
    private Product $ingredient2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RecipeService::class);

        $this->org = Organization::create([
            'name' => 'Recipe Test Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Recipe Store',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        // Allow operations regardless of stock level
        StoreSettings::create([
            'store_id' => $this->store->id,
            'track_inventory' => false,
            'allow_negative_stock' => true,
        ]);

        $this->finishedProduct = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee Latte',
            'sell_price' => 15.00,
            'sync_version' => 1,
        ]);

        $this->ingredient1 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Espresso Beans',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $this->ingredient2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Milk',
            'sell_price' => 2.00,
            'sync_version' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Barista',
            'email' => 'barista@recipe.test',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_creates_recipe_with_ingredients(): void
    {
        $recipe = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'product_id' => $this->finishedProduct->id,
                'name' => 'Latte',
                'yield_quantity' => 1,
                'is_active' => true,
            ],
            ingredients: [
                ['ingredient_product_id' => $this->ingredient1->id, 'quantity' => 18, 'unit' => 'gram', 'waste_percent' => 0],
                ['ingredient_product_id' => $this->ingredient2->id, 'quantity' => 200, 'unit' => 'ml', 'waste_percent' => 5],
            ],
        );

        $this->assertEquals($this->finishedProduct->id, $recipe->product_id);
        $this->assertCount(2, $recipe->recipeIngredients);
    }

    public function test_updates_recipe_ingredients(): void
    {
        $recipe = $this->buildActiveRecipe(beanQty: 18, milkQty: 200);

        $updated = $this->service->update(
            id: $recipe->id,
            organizationId: $this->org->id,
            data: ['yield_quantity' => 2],
            ingredients: [
                ['ingredient_product_id' => $this->ingredient1->id, 'quantity' => 36, 'unit' => 'gram', 'waste_percent' => 0],
            ],
        );

        $this->assertEquals(2.0, (float) $updated->yield_quantity);
        $this->assertCount(1, $updated->recipeIngredients); // Milk removed
    }

    public function test_deletes_recipe_and_ingredients(): void
    {
        $recipe = $this->buildActiveRecipe();

        $this->service->delete($recipe->id, $this->org->id);

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
    }

    public function test_find_recipe_returns_ingredients(): void
    {
        $recipe = $this->buildActiveRecipe();

        $found = $this->service->find($recipe->id, $this->org->id);

        $this->assertEquals($recipe->id, $found->id);
        $this->assertNotEmpty($found->recipeIngredients);
    }

    public function test_find_by_product_id_returns_active_recipe(): void
    {
        $active = $this->buildActiveRecipe();

        // Inactive recipe should not be returned
        Recipe::create([
            'organization_id' => $this->org->id,
            'product_id' => $this->finishedProduct->id,
            'name' => 'Old Latte',
            'yield_quantity' => 1,
            'is_active' => false,
        ]);

        $found = $this->service->findByProductId($this->finishedProduct->id, $this->org->id);

        $this->assertEquals($active->id, $found->id);
    }

    public function test_find_by_product_id_returns_null_when_none(): void
    {
        $result = $this->service->findByProductId($this->finishedProduct->id, $this->org->id);
        $this->assertNull($result);
    }

    // ═══════════════════════════════════════════════════════════
    // deductIngredients
    // ═══════════════════════════════════════════════════════════

    public function test_deduct_ingredients_reduces_stock_proportionally(): void
    {
        $recipe = $this->buildActiveRecipe(beanQty: 18, milkQty: 200, yieldQty: 1);

        $this->seedIngredientStock($this->ingredient1->id, 100);
        $this->seedIngredientStock($this->ingredient2->id, 1000);

        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 2, // selling 2 lattes
            performedBy: $this->user->id,
        );

        // 2 * 18 = 36 beans consumed
        $this->assertEqualsWithDelta(64.0, $this->getIngredientStock($this->ingredient1->id), 0.001);
        // 2 * 200 = 400 ml milk consumed
        $this->assertEqualsWithDelta(600.0, $this->getIngredientStock($this->ingredient2->id), 0.001);
    }

    public function test_deduct_ingredients_applies_waste_percent(): void
    {
        // 10g beans, 10% waste => actual deduction = 11g per latte
        $recipe = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'product_id' => $this->finishedProduct->id,
                'name' => 'Latte with Waste',
                'yield_quantity' => 1,
                'is_active' => true,
            ],
            ingredients: [
                ['ingredient_product_id' => $this->ingredient1->id, 'quantity' => 10, 'unit' => 'gram', 'waste_percent' => 10],
            ],
        );

        $this->seedIngredientStock($this->ingredient1->id, 100);

        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 3, // 3 lattes => 3 * 10 * 1.10 = 33g
            performedBy: $this->user->id,
        );

        $this->assertEqualsWithDelta(67.0, $this->getIngredientStock($this->ingredient1->id), 0.001);
    }

    public function test_deduct_ingredients_scales_with_yield_quantity(): void
    {
        // yield = 2 means 1 recipe produces 2 lattes
        // beans per recipe: 18g / 2 (yield) = 9g per latte sold
        $recipe = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'product_id' => $this->finishedProduct->id,
                'name' => 'Double Batch Latte',
                'yield_quantity' => 2,
                'is_active' => true,
            ],
            ingredients: [
                ['ingredient_product_id' => $this->ingredient1->id, 'quantity' => 18, 'unit' => 'gram', 'waste_percent' => 0],
            ],
        );

        $this->seedIngredientStock($this->ingredient1->id, 100);

        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 4, // 4 * 18/2 = 36g
            performedBy: $this->user->id,
        );

        $this->assertEqualsWithDelta(64.0, $this->getIngredientStock($this->ingredient1->id), 0.001);
    }

    public function test_deduct_ingredients_idempotency_prevents_double_deduction(): void
    {
        $recipe = $this->buildActiveRecipe(beanQty: 10, milkQty: 100);

        $this->seedIngredientStock($this->ingredient1->id, 100);
        $this->seedIngredientStock($this->ingredient2->id, 500);

        $key = 'txn-idempotent-001';
        $refId = (string) \Illuminate\Support\Str::uuid();

        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 1,
            performedBy: $this->user->id,
            idempotencyKey: $key,
            referenceId: $refId,
        );

        // Retry same call
        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 1,
            performedBy: $this->user->id,
            idempotencyKey: $key,
            referenceId: $refId,
        );

        // Only 10g beans consumed, not 20
        $this->assertEqualsWithDelta(90.0, $this->getIngredientStock($this->ingredient1->id), 0.001);
    }

    public function test_deduct_ingredients_all_or_nothing_on_insufficient_stock(): void
    {
        StoreSettings::where('store_id', $this->store->id)->update([
            'track_inventory' => true,
            'allow_negative_stock' => false,
        ]);

        $recipe = $this->buildActiveRecipe(beanQty: 10, milkQty: 100);

        $this->seedIngredientStock($this->ingredient1->id, 100);
        $this->seedIngredientStock($this->ingredient2->id, 50); // not enough for 10 lattes (1000ml needed)

        $this->expectException(\RuntimeException::class);

        $this->service->deductIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 10, // 10 * 100ml = 1000ml but only 50ml available
            performedBy: $this->user->id,
        );
    }

    // ═══════════════════════════════════════════════════════════
    // reverseIngredients
    // ═══════════════════════════════════════════════════════════

    public function test_reverse_ingredients_restores_stock(): void
    {
        $recipe = $this->buildActiveRecipe(beanQty: 18, milkQty: 200);

        $this->seedIngredientStock($this->ingredient1->id, 82);  // 100 - 18 (already deducted)
        $this->seedIngredientStock($this->ingredient2->id, 800); // 1000 - 200 (already deducted)

        $this->service->reverseIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 1,
            performedBy: $this->user->id,
        );

        $this->assertEqualsWithDelta(100.0, $this->getIngredientStock($this->ingredient1->id), 0.001);
        $this->assertEqualsWithDelta(1000.0, $this->getIngredientStock($this->ingredient2->id), 0.001);
    }

    public function test_reverse_creates_adjustment_in_movements(): void
    {
        $recipe = $this->buildActiveRecipe(beanQty: 10, milkQty: 100);

        $this->seedIngredientStock($this->ingredient1->id, 50);
        $this->seedIngredientStock($this->ingredient2->id, 200);

        $this->service->reverseIngredients(
            recipeId: $recipe->id,
            storeId: $this->store->id,
            quantitySold: 1,
            performedBy: $this->user->id,
        );

        $movements = StockMovement::where('store_id', $this->store->id)
            ->where('type', StockMovementType::AdjustmentIn->value)
            ->get();

        $this->assertCount(2, $movements); // one per ingredient
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function buildActiveRecipe(float $beanQty = 18, float $milkQty = 200, float $yieldQty = 1): Recipe
    {
        return $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'product_id' => $this->finishedProduct->id,
                'name' => 'Latte',
                'yield_quantity' => $yieldQty,
                'is_active' => true,
            ],
            ingredients: [
                ['ingredient_product_id' => $this->ingredient1->id, 'quantity' => $beanQty, 'unit' => 'gram', 'waste_percent' => 0],
                ['ingredient_product_id' => $this->ingredient2->id, 'quantity' => $milkQty, 'unit' => 'ml', 'waste_percent' => 0],
            ],
        );
    }

    private function seedIngredientStock(string $productId, float $qty): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['store_id' => $this->store->id, 'product_id' => $productId],
            ['quantity' => $qty, 'reserved_quantity' => 0, 'average_cost' => 0, 'sync_version' => 1],
        );
    }

    private function getIngredientStock(string $productId): float
    {
        return (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $productId)
            ->value('quantity');
    }
}
