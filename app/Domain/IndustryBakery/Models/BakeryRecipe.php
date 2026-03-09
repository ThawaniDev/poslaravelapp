<?php

namespace App\Domain\IndustryBakery\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BakeryRecipe extends Model
{
    use HasUuids;

    protected $table = 'bakery_recipes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'product_id',
        'name',
        'expected_yield',
        'prep_time_minutes',
        'bake_time_minutes',
        'bake_temperature_c',
        'instructions',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function productionSchedules(): HasMany
    {
        return $this->hasMany(ProductionSchedule::class, 'recipe_id');
    }
}
