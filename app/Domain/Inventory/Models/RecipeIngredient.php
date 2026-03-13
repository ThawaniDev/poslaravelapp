<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasUuids;

    protected $table = 'recipe_ingredients';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'ingredient_product_id',
        'quantity',
        'unit',
        'waste_percent',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'waste_percent' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
    public function ingredientProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
