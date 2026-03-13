<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\Recipe;
use App\Domain\Inventory\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * List recipes for an organization.
     */
    public function list(string $organizationId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Recipe::where('organization_id', $organizationId)
            ->with(['product', 'recipeIngredients.ingredientProduct'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get a single recipe with ingredients.
     */
    public function find(string $id): Recipe
    {
        return Recipe::with(['product', 'recipeIngredients.ingredientProduct'])->findOrFail($id);
    }

    /**
     * Create a recipe with ingredients.
     */
    public function create(array $data, array $ingredients): Recipe
    {
        return DB::transaction(function () use ($data, $ingredients) {
            $recipe = Recipe::create([
                'organization_id' => $data['organization_id'],
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'yield_quantity' => $data['yield_quantity'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($ingredients as $ing) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_product_id' => $ing['ingredient_product_id'],
                    'quantity' => $ing['quantity'],
                    'unit' => $ing['unit'] ?? 'piece',
                    'waste_percent' => $ing['waste_percent'] ?? 0,
                ]);
            }

            return $recipe->load('recipeIngredients');
        });
    }

    /**
     * Update recipe and optionally replace ingredients.
     */
    public function update(string $id, array $data, ?array $ingredients = null): Recipe
    {
        return DB::transaction(function () use ($id, $data, $ingredients) {
            $recipe = Recipe::findOrFail($id);
            $recipe->update(array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'yield_quantity' => $data['yield_quantity'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($v) => $v !== null));

            if ($ingredients !== null) {
                $recipe->recipeIngredients()->delete();
                foreach ($ingredients as $ing) {
                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_product_id' => $ing['ingredient_product_id'],
                        'quantity' => $ing['quantity'],
                        'unit' => $ing['unit'] ?? 'piece',
                        'waste_percent' => $ing['waste_percent'] ?? 0,
                    ]);
                }
            }

            return $recipe->fresh(['product', 'recipeIngredients.ingredientProduct']);
        });
    }

    /**
     * Delete recipe.
     */
    public function delete(string $id): void
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->recipeIngredients()->delete();
        $recipe->delete();
    }

    /**
     * Deduct ingredients from stock when a recipe product is sold.
     * Uses waste_percent to calculate actual deduction.
     */
    public function deductIngredients(string $recipeId, string $storeId, float $quantitySold, string $performedBy): void
    {
        $recipe = Recipe::with('recipeIngredients')->findOrFail($recipeId);

        DB::transaction(function () use ($recipe, $storeId, $quantitySold, $performedBy) {
            foreach ($recipe->recipeIngredients as $ingredient) {
                // Calculate quantity including waste
                $baseQty = $ingredient->quantity * $quantitySold / ($recipe->yield_quantity ?: 1);
                $wasteMultiplier = 1 + (($ingredient->waste_percent ?? 0) / 100);
                $deductQty = $baseQty * $wasteMultiplier;

                $this->stockService->adjustStock(
                    storeId: $storeId,
                    productId: $ingredient->ingredient_product_id,
                    type: StockMovementType::RecipeDeduction,
                    quantity: $deductQty,
                    referenceType: StockReferenceType::Recipe,
                    referenceId: $recipe->id,
                    performedBy: $performedBy,
                );
            }
        });
    }
}
