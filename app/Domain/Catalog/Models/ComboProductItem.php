<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboProductItem extends Model
{
    use HasUuids;

    protected $table = 'combo_product_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'combo_product_id',
        'product_id',
        'quantity',
        'is_optional',
    ];

    protected $casts = [
        'is_optional' => 'boolean',
        'quantity' => 'decimal:2',
    ];

    public function comboProduct(): BelongsTo
    {
        return $this->belongsTo(ComboProduct::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
