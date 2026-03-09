<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use HasUuids;

    protected $table = 'goods_receipts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'supplier_id',
        'purchase_order_id',
        'reference_number',
        'status',
        'total_cost',
        'notes',
        'received_by',
        'received_at',
        'confirmed_at',
    ];

    protected $casts = [
        'status' => GoodsReceiptStatus::class,
        'total_cost' => 'decimal:2',
        'received_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }
}
