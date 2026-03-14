<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniProductMapping extends Model
{
    use HasUuids;

    protected $table = 'thawani_product_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'product_id',
        'thawani_product_id',
        'is_published',
        'online_price',
        'display_order',
        'last_synced_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'online_price' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
