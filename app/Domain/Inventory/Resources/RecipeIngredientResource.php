<?php

namespace App\Domain\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_id' => $this->recipe_id,
            'ingredient_product_id' => $this->ingredient_product_id,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'waste_percent' => (float) $this->waste_percent,

            'ingredient_product' => $this->whenLoaded('ingredientProduct', fn () => [
                'id' => $this->ingredientProduct->id,
                'name' => $this->ingredientProduct->name,
                'sku' => $this->ingredientProduct->sku,
            ]),
        ];
    }
}
