<?php

namespace App\Domain\PosTerminal\Models;

use App\Domain\Promotion\Enums\DiscountType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasUuids;

    protected $table = 'transaction_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'barcode',
        'product_name',
        'product_name_ar',
        'quantity',
        'unit_price',
        'cost_price',
        'discount_amount',
        'discount_type',
        'discount_value',
        'tax_rate',
        'tax_amount',
        'line_total',
        'serial_number',
        'batch_number',
        'expiry_date',
        'modifier_selections',
        'notes',
        'is_return_item',
        'age_verified',
        'age_verified_at',
        'age_verified_by',
    ];

    protected $casts = [
        'discount_type' => DiscountType::class,
        'modifier_selections' => 'array',
        'is_return_item' => 'boolean',
        'age_verified' => 'boolean',
        'age_verified_at' => 'datetime',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
