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
    public function find(string $id, string $organizationId): Recipe
    {
        return Recipe::where('organization_id', $organizationId)
            ->with(['product', 'recipeIngredients.ingredientProduct'])
            ->findOrFail($id);
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
    public function update(string $id, string $organizationId, array $data, ?array $ingredients = null): Recipe
    {
        return DB::transaction(function () use ($id, $organizationId, $data, $ingredients) {
            $recipe = Recipe::where('organization_id', $organizationId)->findOrFail($id);
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
    public function delete(string $id, string $organizationId): void
    {
        $recipe = Recipe::where('organization_id', $organizationId)->findOrFail($id);
        $recipe->recipeIngredients()->delete();
        $recipe->delete();
    }

    /**
     * Find the active recipe whose product is being sold (or null if none).
     */
    public function findByProductId(string $productId, string $organizationId): ?Recipe
    {
        return Recipe::where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with('recipeIngredients')
            ->first();
    }

    /**
     * Deduct ingredients from stock when a recipe product is sold.
     * Uses waste_percent to calculate actual deduction.
     *
     * Pass referenceType/referenceId so the resulting stock_movements link
     * back to the originating transaction (or production order). Pass
     * idempotencyKey so a retry of the same sale doesn't double-deduct.
     */
    public function deductIngredients(
        string $recipeId,
        string $storeId,
        float $quantitySold,
        string $performedBy,
        ?StockReferenceType $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
    ): void {
        $recipe = Recipe::with('recipeIngredients.ingredientProduct')->findOrFail($recipeId);
        $yield = (float) ($recipe->yield_quantity ?: 1);
        if ($yield <= 0) {
            return; // Defensive: skip recipes with no yield to avoid divide-by-zero.
        }

        DB::transaction(function () use ($recipe, $storeId, $quantitySold, $performedBy, $referenceType, $referenceId, $idempotencyKey, $yield) {
            // Pre-check every ingredient so we either deduct all or none.
            foreach ($recipe->recipeIngredients as $ingredient) {
                $baseQty = (float) $ingredient->quantity * $quantitySold / $yield;
                $wasteMultiplier = 1 + (((float) ($ingredient->waste_percent ?? 0)) / 100);
                $deductQty = $baseQty * $wasteMultiplier;

                $this->stockService->assertSufficientStock(
                    storeId: $storeId,
                    productId: $ingredient->ingredient_product_id,
                    needed: $deductQty,
                    productName: $ingredient->ingredientProduct?->name,
                );
            }

            foreach ($recipe->recipeIngredients as $ingredient) {
                $baseQty = (float) $ingredient->quantity * $quantitySold / $yield;
                $wasteMultiplier = 1 + (((float) ($ingredient->waste_percent ?? 0)) / 100);
                $deductQty = $baseQty * $wasteMultiplier;

                $perIngredientKey = $idempotencyKey
                    ? substr(hash('sha256', $idempotencyKey . ':deduct:' . $ingredient->ingredient_product_id), 0, 64)
                    : null;

                $this->stockService->adjustStock(
                    storeId: $storeId,
                    productId: $ingredient->ingredient_product_id,
                    type: StockMovementType::RecipeDeduction,
                    quantity: $deductQty,
                    referenceType: $referenceType ?? StockReferenceType::Recipe,
                    referenceId: $referenceId ?? $recipe->id,
                    performedBy: $performedBy,
                    idempotencyKey: $perIngredientKey,
                );
            }
        });
    }

    /**
     * Restore ingredients to stock when a sale that consumed this recipe
     * is voided/refunded. Mirror of deductIngredients (same WAC math).
     */
    public function reverseIngredients(
        string $recipeId,
        string $storeId,
        float $quantitySold,
        string $performedBy,
        ?StockReferenceType $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
    ): void {
        $recipe = Recipe::with('recipeIngredients')->findOrFail($recipeId);
        $yield = (float) ($recipe->yield_quantity ?: 1);
        if ($yield <= 0) {
            return;
        }

        DB::transaction(function () use ($recipe, $storeId, $quantitySold, $performedBy, $referenceType, $referenceId, $idempotencyKey, $yield) {
            foreach ($recipe->recipeIngredients as $ingredient) {
                $baseQty = (float) $ingredient->quantity * $quantitySold / $yield;
                $wasteMultiplier = 1 + (((float) ($ingredient->waste_percent ?? 0)) / 100);
                $restoreQty = $baseQty * $wasteMultiplier;

                $perIngredientKey = $idempotencyKey
                    ? substr(hash('sha256', $idempotencyKey . ':reverse:' . $ingredient->ingredient_product_id), 0, 64)
                    : null;

                $this->stockService->adjustStock(
                    storeId: $storeId,
                    productId: $ingredient->ingredient_product_id,
                    type: StockMovementType::AdjustmentIn,
                    quantity: $restoreQty,
                    referenceType: $referenceType ?? StockReferenceType::Recipe,
                    referenceId: $referenceId ?? $recipe->id,
                    reason: 'Recipe deduction reversal',
                    performedBy: $performedBy,
                    idempotencyKey: $perIngredientKey,
                );
            }
        });
    }
}
