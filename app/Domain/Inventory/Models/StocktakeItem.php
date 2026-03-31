<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    use HasUuids;

    protected $table = 'stocktake_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'stocktake_id',
        'product_id',
        'expected_qty',
        'counted_qty',
        'variance',
        'cost_impact',
        'notes',
        'counted_at',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'variance' => 'decimal:3',
        'cost_impact' => 'decimal:2',
        'counted_at' => 'datetime',
    ];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
