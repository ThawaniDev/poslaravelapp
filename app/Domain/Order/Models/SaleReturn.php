<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\ReturnRefundMethod;
use App\Domain\Order\Enums\ReturnType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasUuids;

    protected $table = 'returns';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'order_id',
        'return_number',
        'type',
        'reason_code',
        'refund_method',
        'subtotal',
        'tax_amount',
        'total_refund',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'type' => ReturnType::class,
        'refund_method' => ReturnRefundMethod::class,
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_refund' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class);
    }
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
