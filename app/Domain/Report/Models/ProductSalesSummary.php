<?php

namespace App\Domain\Report\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSalesSummary extends Model
{
    use HasUuids;

    protected $table = 'product_sales_summary';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_id',
        'date',
        'quantity_sold',
        'revenue',
        'cost',
        'discount_amount',
        'tax_amount',
        'return_quantity',
        'return_amount',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:2',
        'revenue' => 'decimal:2',
        'cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'return_quantity' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'date' => 'date',
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
