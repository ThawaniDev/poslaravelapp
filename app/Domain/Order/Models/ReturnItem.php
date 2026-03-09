<?php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasUuids;

    protected $table = 'return_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'return_id',
        'order_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'refund_amount',
        'restore_stock',
    ];

    protected $casts = [
        'restore_stock' => 'boolean',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
